<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\NexusThresholdSource;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;

/**
 * An in-memory {@see NexusThresholdSource} for tests — a map of state code to its
 * threshold, so a suite need not stand up the dataset.
 */
readonly class ArrayNexusThresholdSource implements NexusThresholdSource
{
    /**
     * @param  array<string, EconomicNexusThreshold>  $thresholds  state code => threshold
     */
    public function __construct(private array $thresholds = []) {}

    public function thresholdFor(SubdivisionCode $state): ?EconomicNexusThreshold
    {
        return $this->thresholds[$state->value] ?? null;
    }
}
