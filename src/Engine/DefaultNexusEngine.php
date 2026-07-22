<?php

declare(strict_types=1);

namespace Cbox\Nexus\Engine;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Nexus\Contracts\NexusRegistrations;
use Cbox\Nexus\Contracts\NexusThresholdSource;
use Cbox\Nexus\Contracts\PhysicalNexus;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use Cbox\Nexus\ValueObjects\NexusEvaluation;
use Cbox\Nexus\ValueObjects\NexusReport;
use Cbox\Nexus\ValueObjects\SellerActivity;

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
 * Deny-by-default: with no threshold or no recorded activity it makes no economic
 * claim (Below), and it NEVER accumulates sales or infers nexus from one supply —
 * economic nexus turns on cumulative totals the host supplies via {@see SalesLedger}.
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

    public function evaluate(SubdivisionCode $state): NexusEvaluation
    {
        $threshold = $this->thresholds->thresholdFor($state);
        $activity = $this->ledger->activityFor($state);
        $physical = $this->physical->hasPresenceIn($state);

        if ($this->registrations->isRegisteredIn($state)) {
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

        if ($threshold === null || $activity === null) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Below,
                $threshold,
                $activity,
                $this->progress($threshold, $activity),
                false,
                $threshold === null
                    ? sprintf('No economic-nexus threshold known for %s.', $state->value)
                    : sprintf('No recorded activity in %s.', $state->value),
            );
        }

        if ($threshold->isMet($activity->salesDollars, $activity->transactions)) {
            return new NexusEvaluation(
                $state,
                NexusStatus::Triggered,
                $threshold,
                $activity,
                $this->progress($threshold, $activity),
                false,
                sprintf('Economic nexus met in %s (%s) — register.', $state->value, $threshold->describe()),
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
        );
    }

    /**
     * @param  list<SubdivisionCode>  $states
     */
    public function report(array $states): NexusReport
    {
        return new NexusReport(array_map(
            fn (SubdivisionCode $state): NexusEvaluation => $this->evaluate($state),
            $states,
        ));
    }

    private function progress(?EconomicNexusThreshold $threshold, ?SellerActivity $activity): ?float
    {
        if ($threshold === null || $activity === null) {
            return null;
        }

        return $threshold->progress($activity->salesDollars, $activity->transactions);
    }
}
