<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use Cbox\Nexus\Engine\DefaultNexusEngine;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use DateTimeImmutable;

/**
 * A seller's CUMULATIVE sales into one state over the state's measurement period —
 * the running totals economic nexus turns on. The host owns accumulation (it has
 * the invoice data and knows the state's measurement window and sales basis); the
 * engine only compares these totals to the threshold. Sales are whole US dollars.
 *
 * `basis` and `period` are what the host DECLARES it counted, and they are the
 * difference between an answer and a coincidence. Florida's $100,000 is *taxable*
 * sales over the *previous calendar year*; Texas's $500,000 is gross receipts over
 * a *rolling twelve months*. Feed either one calendar-YTD gross totals and the
 * engine returns a confident verdict about a question the state did not ask.
 *
 * The engine cannot recompute the totals — it never sees an invoice. But given the
 * declaration it can refuse when the host counted something other than what the
 * state measures, which is what {@see DefaultNexusEngine} does.
 *
 * Both are nullable so an existing host keeps working: an undeclared basis is
 * carried onto the evaluation as a caveat rather than silently assumed correct.
 */
readonly class SellerActivity
{
    public function __construct(
        public int $salesDollars,
        public int $transactions,
        public ?DateTimeImmutable $periodStart = null,
        public ?DateTimeImmutable $periodEnd = null,
        /** Which sales the host counted. Null = undeclared. */
        public ?NexusSalesBasis $basis = null,
        /** The window the host accumulated over. Null = undeclared. */
        public ?NexusMeasurementPeriod $period = null,
    ) {}
}
