<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use DateTimeImmutable;

/**
 * Supplies a state's economic-nexus threshold. The shipped implementation reads
 * the us-tax-data dataset; a host may bind its own. A state with no known
 * threshold (the no-sales-tax states, or one not carried) returns null — the
 * engine then makes no economic claim there (deny-by-default).
 */
interface NexusThresholdSource
{
    /**
     * `$at` is the date the question is asked as of, null meaning today.
     *
     * Thresholds are dated law and they move: Kentucky's 200-transaction test was
     * repealed with effect from 2026-08-01. Evaluating every window against the
     * current date makes a filing prepared in arrears use a threshold that did not
     * exist in the period it covers — a seller who genuinely crossed a $100,000
     * threshold in 2025 is told they were below the $500,000 one that replaced it.
     */
    public function thresholdFor(SubdivisionCode $state, ?DateTimeImmutable $at = null): ?EconomicNexusThreshold;
}
