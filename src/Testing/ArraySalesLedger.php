<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\ValueObjects\SellerActivity;

/**
 * An in-memory {@see SalesLedger} for tests and simple setups — a map of state code
 * to the seller's accumulated activity. Dogfooded by this package's own suite.
 */
readonly class ArraySalesLedger implements SalesLedger
{
    /**
     * @param  array<string, SellerActivity>  $activity  state code => cumulative activity
     */
    public function __construct(private array $activity = []) {}

    public function activityFor(SubdivisionCode $state): ?SellerActivity
    {
        return $this->activity[$state->value] ?? null;
    }
}
