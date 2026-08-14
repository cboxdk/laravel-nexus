<?php

declare(strict_types=1);

namespace Cbox\Nexus\Engine;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\KnowsNonTaxingStates;
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Nexus\Contracts\NexusRegistrations;
use Cbox\Nexus\Contracts\NexusThresholdSource;
use Cbox\Nexus\Contracts\PhysicalNexus;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use Cbox\Nexus\ValueObjects\MeasurementWindow;
use Cbox\Nexus\ValueObjects\NexusEvaluation;
use Cbox\Nexus\ValueObjects\NexusReport;
use Cbox\Nexus\ValueObjects\NexusSubject;
use Cbox\Nexus\ValueObjects\SellerActivity;
use DateTimeImmutable;

/**
 * The shipped economic-nexus engine. For each state it resolves, in precedence
 * order:
 *
 *  1. Already registered → {@see NexusStatus::Registered} (obligation handled).
 *  2. Physical presence → {@see NexusStatus::Triggered} (a nexus trigger on its own).
 *  3. Cumulative activity crosses the threshold → {@see NexusStatus::Triggered}.
 *  4. Activity within the warning band of the threshold → {@see NexusStatus::Approaching}.
 *  5. Otherwise → {@see NexusStatus::Below}.
 *
 * Deny-by-default: an unresolvable threshold, an unavailable ledger, or totals
 * counted on a different basis than the state measures all yield
 * {@see NexusStatus::Unknown} rather than a clean verdict. It NEVER accumulates
 * sales or infers nexus from one supply — economic nexus turns on cumulative
 * totals the host supplies via {@see SalesLedger}.
 *
 * What it can and cannot check is worth stating plainly. It cannot recompute the
 * host's totals; it never sees an invoice. What it CAN do, given the basis and
 * period the host declares on {@see SellerActivity}, is notice when those disagree
 * with what the state actually measures — the single most likely way a host gets
 * this wrong — and refuse instead of answering a different question confidently.
 * An undeclared basis is not treated as agreement: the verdict stands but carries
 * a caveat, because a seller reading "Economic nexus met — register" deserves to
 * know the engine never verified which sales that rests on.
 */
readonly class DefaultNexusEngine implements NexusEngine
{
    public function __construct(
        private NexusThresholdSource $thresholds,
        private SalesLedger $ledger,
        private PhysicalNexus $physical,
        private NexusRegistrations $registrations,
        /** Fraction of the threshold at which a state is flagged "approaching" (0–1). */
        private float $approachingRatio = 0.8,
    ) {}

    public function evaluate(
        SubdivisionCode $state,
        ?NexusSubject $subject = null,
        ?DateTimeImmutable $asOf = null,
    ): NexusEvaluation {
        // The threshold is resolved FIRST because it defines the question put to the
        // ledger. Asked only for a state, an implementation had to look the
        // threshold up itself to know that Florida wants taxable sales over the
        // previous calendar year while Texas wants gross receipts over a rolling
        // twelve months — reaching past this contract in order to satisfy it.
        $threshold = $this->thresholds->thresholdFor($state, $asOf);

        $query = new ActivityQuery(
            state: $state,
            subject: $subject,
            period: $threshold?->measuringPeriod,
            basis: $threshold?->salesBasis,
            includesMarketplaceSales: $threshold?->includesMarketplaceSales,
            asOf: $asOf,
        );

        $activity = $this->ledger->activityFor($query);

        $physical = $this->physical->hasPresenceIn($state, $subject);

        if ($this->registrations->isRegisteredIn($state, $subject)) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Registered,
                $threshold,
                $activity,
                $this->progress($threshold, $activity),
                $physical,
                sprintf('Registered in %s — obligation handled.', $state->value),
            );
        }

        // Before any obligation is INFERRED, ask whether the state levies the tax at
        // all. Physical presence in Oregon is real presence and does establish nexus
        // — but nexus to what? There is no general sales tax there to register for,
        // and telling a seller to register hands them a filing obligation, due dates
        // and penalties in exchange for nothing. An explicit registration above is
        // the host stating a fact about itself and is left standing; this package
        // only declines to invent one.
        if ($this->leviesNoSalesTax($state)) {
            return new NexusEvaluation(
                $state,
                NexusStatus::NotApplicable,
                null,
                $activity,
                null,
                $physical,
                sprintf('%s levies no general sales tax — no threshold to cross.', $state->value),
            );
        }

        if ($physical) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Triggered,
                $threshold,
                $activity,
                $this->progress($threshold, $activity),
                true,
                sprintf('Physical presence in %s establishes nexus — register.', $state->value),
            );
        }

        // An UNRESOLVABLE THRESHOLD is not the same as being below one. The threshold source is
        // remote, so a firewalled or misconfigured deployment resolves null for every state — and
        // reporting `Below` there reads as "no obligation" when the honest answer is "unknown".
        // A seller could cross every threshold in the country and see a clean board.
        if ($threshold === null) {
            // The no-general-sales-tax case (Delaware, Montana, New Hampshire,
            // Oregon) has already returned above: those states have no threshold
            // because there is nothing to cross, and reporting them as Unknown puts
            // four standing false alarms on every healthy install.
            return new NexusEvaluation(
                $state,
                NexusStatus::Unknown,
                null,
                $activity,
                $this->progress(null, $activity),
                false,
                sprintf(
                    'No economic-nexus threshold is available for %s, so nexus cannot be evaluated. '
                    .'Check the threshold dataset is reachable.',
                    $state->value,
                ),
            );
        }

        // NO ACTIVITY is not "nothing was sold" — it is the LEDGER having nothing to
        // say. A real ledger answering for a quiet state returns zero totals, not
        // null; null is what an unbound, unreachable or unpopulated ledger returns,
        // and reading it as Below hands back a clean board for all fifty states to
        // a host that simply forgot to wire one. That is the same failure `Unknown`
        // was introduced to stop at the threshold seam, one seam further out.
        if ($activity === null) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Unknown,
                $threshold,
                null,
                null,
                false,
                sprintf(
                    'No activity is recorded for %s, so nexus cannot be evaluated. Bind a SalesLedger that '
                    .'reports the seller\'s cumulative totals; return zero totals to assert there were none.',
                    $state->value,
                ),
            );
        }

        $mismatch = $this->undecidableMismatch($threshold, $activity, $query);

        // Counting the wrong sales, or over the wrong window, does not produce a
        // slightly-off verdict — it produces a confident answer to a question the
        // state never asked. Florida measures TAXABLE sales over the PREVIOUS
        // calendar year; gross calendar-YTD totals compared against it are not an
        // approximation of the answer, they are a different number entirely.
        if ($mismatch !== null) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Unknown,
                $threshold,
                $activity,
                null,
                false,
                sprintf('Nexus cannot be evaluated for %s: %s', $state->value, $mismatch),
            );
        }

        $caveats = $this->caveats($threshold, $activity);

        if ($threshold->isMet($activity->salesDollars, $activity->transactions)) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Triggered,
                $threshold,
                $activity,
                $this->progress($threshold, $activity),
                false,
                sprintf('Economic nexus met in %s (%s) — register.', $state->value, $threshold->describe()),
                $caveats,
            );
        }

        $progress = $threshold->progress($activity->salesDollars, $activity->transactions);
        $status = $progress >= $this->approachingRatio ? NexusStatus::Approaching : NexusStatus::Below;

        return new NexusEvaluation(
            $state,
            $status,
            $threshold,
            $activity,
            $progress,
            false,
            sprintf(
                '%s of the %s threshold reached in %s.',
                number_format($progress * 100, 1).'%',
                $threshold->describe(),
                $state->value,
            ),
            $caveats,
        );
    }

    /**
     * A disagreement the engine cannot reason around, as a phrase for the reason
     * string. Null when the bases agree, when either side left it undeclared, or
     * when the mismatch is DECIDABLE anyway.
     *
     * Decidable is the important word. The bases are nested — gross ⊇ retail ⊇
     * taxable — so a total counted on the wrong one still bounds the right one:
     *
     *  - Counted BROADER than the state measures and still below the threshold?
     *    The narrower figure can only be smaller, so it is below too. Provably
     *    `Below`.
     *  - Counted NARROWER and already over? The broader figure can only be larger,
     *    so it is over too. Provably `Triggered` — and refusing here is the
     *    dangerous direction: it tells a seller who has definitively crossed that
     *    the engine cannot say, when what it owes them is "register".
     *
     * Only the other two combinations are genuinely undecidable, and only those
     * refuse. A period mismatch is always undecidable — a rolling twelve months
     * and a calendar year cover different sales, with no ordering between them.
     */
    private function undecidableMismatch(
        EconomicNexusThreshold $threshold,
        SellerActivity $activity,
        ActivityQuery $query,
    ): ?string {
        if ($threshold->measuringPeriod !== null
            && $activity->period !== null
            && $threshold->measuringPeriod !== $activity->period) {
            return sprintf(
                'the state measures over the %s but the ledger accumulated over the %s, and the two cover different sales.',
                str_replace('_', ' ', $threshold->measuringPeriod->value),
                str_replace('_', ' ', $activity->period->value),
            );
        }

        $window = $this->windowMismatch($threshold, $activity, $query);

        if ($window !== null) {
            return $window;
        }

        $measures = $threshold->salesBasis;
        $counted = $activity->basis;

        if ($measures === null || $counted === null || $measures === $counted) {
            return null;
        }

        $met = $threshold->isMet($activity->salesDollars, $activity->transactions);

        // Broader-and-below, or narrower-and-met: the bound settles it either way.
        if ($counted->isAtLeastAsBroadAs($measures) ? ! $met : $met) {
            return null;
        }

        return sprintf(
            'the state measures %s but the ledger counted %s, which cannot settle a %s verdict either way.',
            $measures->label(),
            $counted->label(),
            $met ? 'met' : 'below',
        );
    }

    /**
     * The DATES the ledger says it accumulated over, checked against the dates the
     * state's rule actually resolves to.
     *
     * The enum check above only compares the ledger's declared RULE with the
     * state's. Two ledgers can both say "rolling twelve months" and mean different
     * twelve months — one computed from a stale as-of date, one from a cache
     * warmed last quarter, one snapping to month boundaries — and the declared
     * rule matches in every case. {@see SellerActivity} has carried the concrete
     * bounds all along; nothing read them, so a ledger reporting last year's
     * window against this year's threshold was accepted without comment.
     *
     * The bounding logic is the same as the basis check's, and for the same
     * reason: the totals are monotone in the window, so a range that merely
     * differs is not automatically undecidable.
     *
     *  - Accumulated over a NARROWER range and already over the threshold? The
     *    required window contains it, so its totals can only be larger. Provably
     *    met — and refusing here would tell a seller who has definitively crossed
     *    that the engine cannot say.
     *  - Accumulated over a BROADER range and still below? The required window's
     *    totals can only be smaller. Provably below.
     *
     * Only a partial overlap, or a range that misses in the deciding direction,
     * genuinely cannot settle it.
     *
     * Null when the ledger declared no bounds — that is an undeclared window, and
     * {@see caveats()} already says so rather than refusing.
     */
    private function windowMismatch(
        EconomicNexusThreshold $threshold,
        SellerActivity $activity,
        ActivityQuery $query,
    ): ?string {
        if ($activity->periodStart === null || $activity->periodEnd === null) {
            return null;
        }

        $windows = $query->windows();

        if ($windows === []) {
            return null;
        }

        $from = $activity->periodStart->format('Y-m-d');
        $to = $activity->periodEnd->format('Y-m-d');
        $met = $threshold->isMet($activity->salesDollars, $activity->transactions);

        foreach ($windows as $window) {
            $required = [$window->from->format('Y-m-d'), $window->to->format('Y-m-d')];

            if ($from === $required[0] && $to === $required[1]) {
                return null;
            }

            $narrower = $from >= $required[0] && $to <= $required[1];
            $broader = $from <= $required[0] && $to >= $required[1];

            if (($narrower && $met) || ($broader && ! $met)) {
                return null;
            }
        }

        return sprintf(
            'the ledger accumulated over %s to %s, but this state measures %s, and totals over one range cannot settle a %s verdict for the other.',
            $from,
            $to,
            implode(' or ', array_map(
                static fn (MeasurementWindow $w): string => $w->from->format('Y-m-d').' to '.$w->to->format('Y-m-d'),
                $windows,
            )),
            $met ? 'met' : 'below',
        );
    }

    /**
     * What this verdict rests on that the engine could not verify.
     *
     * A caveat is not free. A host that has not adopted the optional `basis` and
     * `period` fields would otherwise collect one on every evaluable state — the
     * same cry-wolf failure {@see NexusStatus::NotApplicable} exists to avoid, and
     * a signal that fires 47 times out of 51 discriminates nothing. So the
     * ledger-wide concerns are stated once per evaluation but deduplicated at the
     * report ({@see NexusReport::distinctCaveats()}), and the per-state ones —
     * where the DATASET is silent — are the ones that actually vary.
     *
     * @return list<string>
     */
    private function caveats(EconomicNexusThreshold $threshold, SellerActivity $activity): array
    {
        $caveats = [];

        if ($threshold->salesBasis !== null && $activity->basis === null) {
            $caveats[] = 'The ledger did not declare which sales it counted, so the totals were taken at face value.';
        }

        if ($threshold->measuringPeriod !== null && $activity->period === null) {
            $caveats[] = 'The ledger did not declare its accumulation window, so the period was taken at face value.';
        }

        // The verdict was reached by BOUNDING rather than by comparing like with
        // like. It is sound — see undecidableMismatch() — but it rests on the
        // nesting of the bases, not on the figure the state would compute.
        if ($threshold->salesBasis !== null
            && $activity->basis !== null
            && $threshold->salesBasis !== $activity->basis) {
            $caveats[] = sprintf(
                'The ledger counted %s where this state measures %s; the verdict follows because one bounds the other, not from a like-for-like total.',
                $activity->basis->label(),
                $threshold->salesBasis->label(),
            );
        }

        if ($threshold->salesBasis === null) {
            $caveats[] = 'The threshold dataset carries no sales basis for this state, so which sales count is unverified.';
        }

        if ($threshold->measuringPeriod === null) {
            $caveats[] = 'The threshold dataset carries no measurement period for this state, so the window is unverified.';
        }

        return $caveats;
    }

    /**
     * @param  list<SubdivisionCode>  $states
     */
    public function report(
        array $states,
        ?NexusSubject $subject = null,
        ?DateTimeImmutable $asOf = null,
    ): NexusReport {
        return new NexusReport(array_map(
            fn (SubdivisionCode $state): NexusEvaluation => $this->evaluate($state, $subject, $asOf),
            $states,
        ));
    }

    /**
     * Whether the source can POSITIVELY state that this state levies no general
     * sales tax. Absence of a threshold is not that statement — an unreachable
     * dataset also has no threshold — so only a source that models the distinction
     * can answer, and anything else stays unknown.
     */
    private function leviesNoSalesTax(SubdivisionCode $state): bool
    {
        return $this->thresholds instanceof KnowsNonTaxingStates
            && $this->thresholds->leviesNoSalesTax($state);
    }

    private function progress(?EconomicNexusThreshold $threshold, ?SellerActivity $activity): ?float
    {
        if ($threshold === null || $activity === null) {
            return null;
        }

        return $threshold->progress($activity->salesDollars, $activity->transactions);
    }
}
