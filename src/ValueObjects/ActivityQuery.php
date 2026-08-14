<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use DateTimeImmutable;

/**
 * Everything a {@see SalesLedger} needs to answer with the RIGHT totals.
 *
 * The seam used to be `activityFor(SubdivisionCode $state)`, and it asked an
 * incomplete question. The engine would take whatever came back and then check
 * whether the basis and period the host declared matched what the state actually
 * measures — refusing when they didn't. So the ledger had to work out, on its own,
 * that Florida wants taxable sales over the previous calendar year while Texas
 * wants gross receipts over a rolling twelve months. The only way to know that was
 * to look up the threshold itself: the implementation had to reach past the
 * contract in order to satisfy it.
 *
 * Now the question carries its own terms. The engine resolves the state's threshold
 * first and asks for exactly what that state measures, over {@see windows()}. The
 * host still owns the accumulation — it has the invoices and this package never
 * sees one — but it no longer has to rediscover the rules to run the sum.
 */
readonly class ActivityQuery
{
    public function __construct(
        public SubdivisionCode $state,
        /**
         * WHO to answer for. Null when the host serves a single seller.
         *
         * A multi-tenant implementation that receives null MUST refuse — return
         * null, which the engine reports as `Unknown` — rather than fall back on
         * ambient request or container state. Falling back is how the wrong
         * tenant's totals get returned for the right state, and it fails silently.
         */
        public ?NexusSubject $subject = null,
        /** The window the state measures over. Null when the threshold is unknown. */
        public ?NexusMeasurementPeriod $period = null,
        /** Which sales the state counts. Null when the threshold is unknown. */
        public ?NexusSalesBasis $basis = null,
        /**
         * Whether the state counts marketplace-facilitated sales toward the
         * threshold. Null when the dataset does not say.
         */
        public ?bool $includesMarketplaceSales = null,
        /** The date the question is asked as of. Null means today. */
        public ?DateTimeImmutable $asOf = null,
    ) {}

    public function on(): DateTimeImmutable
    {
        return $this->asOf ?? new DateTimeImmutable('today');
    }

    /**
     * The concrete date ranges to accumulate over.
     *
     * This is the part the host should not have had to work out. "Rolling twelve
     * months" and "previous calendar year" are rules, not dates, and turning them
     * into dates is the same arithmetic in every implementation — so it belongs
     * here rather than in each of them.
     *
     * USUALLY ONE WINDOW, SOMETIMES TWO. A state measuring the previous *or*
     * current calendar year is met if EITHER year crosses, which is genuinely two
     * separate sums and not one longer one — adding the years together would cross
     * a threshold neither year reached. Report the window with the higher totals:
     * where the test is "either", the larger figure is the one that decides it.
     *
     * An empty list means the period is unknown, and there is nothing to derive.
     *
     * @return list<MeasurementWindow>
     */
    public function windows(): array
    {
        if ($this->period === null) {
            return [];
        }

        $asOf = $this->on();
        $year = (int) $asOf->format('Y');

        return match ($this->period) {
            NexusMeasurementPeriod::PreviousCalendarYear => [$this->calendarYear($year - 1)],
            NexusMeasurementPeriod::CurrentCalendarYear => [$this->yearToDate($year, $asOf)],
            NexusMeasurementPeriod::PreviousOrCurrentCalendarYear => [
                $this->calendarYear($year - 1),
                $this->yearToDate($year, $asOf),
            ],
            NexusMeasurementPeriod::RollingTwelveMonths => [$this->trailingYear($asOf)],
        };
    }

    /**
     * The twelve months ending on `$asOf`, both ends inclusive.
     *
     * Texas measures "total Texas revenue in the preceding twelve months" and the
     * Comptroller evaluates it at ANY point in time — the clock does not reset in
     * January and it does not snap to month boundaries. An earlier version started
     * this window at the first of the month eleven months back, which quietly
     * dropped the first partial month: a $500,000 sale on 20 August fell outside a
     * window beginning 1 September, and a seller who had unambiguously crossed the
     * threshold was reported Below.
     *
     * A day short of a year back, so the pair spans exactly twelve months with both
     * endpoints counted.
     */
    private function trailingYear(DateTimeImmutable $asOf): MeasurementWindow
    {
        $start = $asOf->modify('-1 year')->modify('+1 day')->setTime(0, 0);

        return new MeasurementWindow($start, $asOf, 'rolling 12 months to '.$asOf->format('Y-m-d'));
    }

    private function calendarYear(int $year): MeasurementWindow
    {
        return new MeasurementWindow(
            new DateTimeImmutable(sprintf('%04d-01-01', $year)),
            new DateTimeImmutable(sprintf('%04d-12-31', $year)),
            (string) $year,
        );
    }

    private function yearToDate(int $year, DateTimeImmutable $asOf): MeasurementWindow
    {
        return new MeasurementWindow(
            new DateTimeImmutable(sprintf('%04d-01-01', $year)),
            $asOf,
            $year.' to date',
        );
    }
}
