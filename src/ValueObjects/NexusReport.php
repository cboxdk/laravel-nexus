<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use Cbox\Nexus\Enums\NexusStatus;

/**
 * A platform-wide roll-up of {@see NexusEvaluation}s across the states a seller
 * sells into — the dashboard view: where nexus has been triggered (act now), where
 * it is approaching (watch), and where the seller is already registered.
 */
readonly class NexusReport
{
    /**
     * @param  list<NexusEvaluation>  $evaluations
     */
    public function __construct(public array $evaluations) {}

    /**
     * States where a registration obligation has likely been triggered.
     *
     * @return list<NexusEvaluation>
     */
    public function triggered(): array
    {
        return $this->withStatus(NexusStatus::Triggered);
    }

    /**
     * States nearing their threshold — worth watching.
     *
     * @return list<NexusEvaluation>
     */
    public function approaching(): array
    {
        return $this->withStatus(NexusStatus::Approaching);
    }

    /**
     * States where the seller already holds a registration.
     *
     * @return list<NexusEvaluation>
     */
    public function registered(): array
    {
        return $this->withStatus(NexusStatus::Registered);
    }

    /**
     * States whose threshold could not be resolved, so no standing could be determined.
     *
     * Surfaced as its own bucket because it is an operational fault, not a nexus outcome: every
     * state landing here usually means the threshold dataset is unreachable, and a report that
     * folds them in with genuine `Below` results looks like a clean bill of health.
     *
     * @return list<NexusEvaluation>
     */
    public function unknown(): array
    {
        return $this->withStatus(NexusStatus::Unknown);
    }

    /**
     * States that levy no general sales tax, so there is nothing to comply with.
     *
     * Its own bucket rather than folded into {@see unknown()}: those four states
     * are a settled answer, and mixing them into the "could not evaluate" pile is
     * what made a healthy install look like it had four standing problems.
     *
     * @return list<NexusEvaluation>
     */
    public function notApplicable(): array
    {
        return $this->withStatus(NexusStatus::NotApplicable);
    }

    /**
     * Every state the seller must act on — triggered obligations AND states that
     * could not be evaluated.
     *
     * Exposed because the natural dashboard is assembled from the named buckets,
     * and a consumer that renders triggered/approaching/registered and stops has
     * silently dropped the unknowns. One question is harder to get wrong than
     * remembering to ask four.
     *
     * @return list<NexusEvaluation>
     */
    public function needingAction(): array
    {
        return array_values(array_filter(
            $this->evaluations,
            static fn (NexusEvaluation $e): bool => $e->needsAction(),
        ));
    }

    /**
     * Every distinct caveat across the report, each stated once.
     *
     * Most caveats are properties of the LEDGER, not of a state — "the ledger did
     * not declare which sales it counted" is identical on all fifty. Rendered per
     * state that is fifty lines of the same sentence, which reads as noise and gets
     * scrolled past, taking the per-state caveats that DO vary with it. Ask for
     * them here and show each concern once.
     *
     * @return list<string>
     */
    public function distinctCaveats(): array
    {
        $seen = [];

        foreach ($this->evaluations as $evaluation) {
            foreach ($evaluation->caveats as $caveat) {
                $seen[$caveat] = true;
            }
        }

        return array_keys($seen);
    }

    public function forState(string $state): ?NexusEvaluation
    {
        foreach ($this->evaluations as $evaluation) {
            if ($evaluation->state->value === $state) {
                return $evaluation;
            }
        }

        return null;
    }

    /**
     * @return list<NexusEvaluation>
     */
    private function withStatus(NexusStatus $status): array
    {
        return array_values(array_filter(
            $this->evaluations,
            static fn (NexusEvaluation $e): bool => $e->status === $status,
        ));
    }
}
