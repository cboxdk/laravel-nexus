<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use DateTimeImmutable;

/**
 * A seller's CUMULATIVE sales into one state over the state's measurement period —
 * the running totals economic nexus turns on. The host owns accumulation (it has
 * the invoice data and knows the state's measurement window and sales basis); the
 * engine only compares these totals to the threshold. Sales are whole US dollars.
 */
readonly class SellerActivity
{
    public function __construct(
        public int $salesDollars,
        public int $transactions,
        public ?DateTimeImmutable $periodStart = null,
        public ?DateTimeImmutable $periodEnd = null,
    ) {}
}
