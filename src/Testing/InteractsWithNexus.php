<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Nexus\Engine\DefaultNexusEngine;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use Cbox\Nexus\ValueObjects\SellerActivity;

/**
 * Test helper: build a {@see NexusEngine} from in-memory fakes and construct the
 * common value objects without ceremony. Dogfooded by this package's own suite —
 * if a fake is awkward here, fix the fake.
 */
trait InteractsWithNexus
{
    /**
     * @param  array<string, EconomicNexusThreshold>  $thresholds  state => threshold
     * @param  array<string, SellerActivity>  $activity  state => cumulative activity
     * @param  list<string>  $physical  states with physical presence
     * @param  list<string>  $registered  states already registered
     */
    protected function nexusEngine(
        array $thresholds = [],
        array $activity = [],
        array $physical = [],
        array $registered = [],
        float $approachingRatio = 0.8,
    ): NexusEngine {
        return new DefaultNexusEngine(
            new ArrayNexusThresholdSource($thresholds),
            new ArraySalesLedger($activity),
            new ArrayPhysicalNexus($physical),
            new ArrayNexusRegistrations($registered),
            $approachingRatio,
        );
    }

    /** A sales-only threshold (the common post-Wayfair shape). */
    protected function salesThreshold(int $dollars): EconomicNexusThreshold
    {
        return new EconomicNexusThreshold($dollars, null, NexusCombinator::SalesOnly);
    }

    /** Cumulative activity for a state. */
    protected function activity(int $dollars, int $transactions = 0): SellerActivity
    {
        return new SellerActivity($dollars, $transactions);
    }
}
