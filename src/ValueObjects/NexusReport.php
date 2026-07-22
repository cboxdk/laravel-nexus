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
