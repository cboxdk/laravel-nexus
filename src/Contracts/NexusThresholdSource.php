<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;

/**
 * Supplies a state's economic-nexus threshold. The shipped implementation reads
 * the us-tax-data dataset; a host may bind its own. A state with no known
 * threshold (the no-sales-tax states, or one not carried) returns null — the
 * engine then makes no economic claim there (deny-by-default).
 */
interface NexusThresholdSource
{
    public function thresholdFor(SubdivisionCode $state): ?EconomicNexusThreshold;
}
