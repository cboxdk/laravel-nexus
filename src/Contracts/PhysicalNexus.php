<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;

/**
 * Asserts the seller's PHYSICAL presence in a state (an office, employees,
 * inventory/FBA) — a nexus trigger independent of the economic thresholds. Host-
 * asserted; the package cannot infer it. Deny-by-default: no assertion means no
 * physical nexus is claimed.
 */
interface PhysicalNexus
{
    public function hasPresenceIn(SubdivisionCode $state): bool;
}
