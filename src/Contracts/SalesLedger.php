<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\ValueObjects\SellerActivity;

/**
 * Supplies the seller's CUMULATIVE activity into a state over its measurement
 * period — the running sales/transaction totals economic nexus turns on. This is
 * the host's data (it owns the invoices and knows the measurement window); the
 * package ships no default. Null means "no activity recorded" — treated as zero.
 */
interface SalesLedger
{
    public function activityFor(SubdivisionCode $state): ?SellerActivity;
}
