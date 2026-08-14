<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\ValueObjects\NexusSubject;

/**
 * Asserts the seller's PHYSICAL presence in a state (an office, employees,
 * inventory/FBA) — a nexus trigger independent of the economic thresholds. Host-
 * asserted; the package cannot infer it. Deny-by-default: no assertion means no
 * physical nexus is claimed.
 *
 * `$subject` is WHO is being asked about, and is null when the host serves a single
 * seller. An implementation serving several that receives null should answer false
 * — deny-by-default — rather than infer the seller from ambient context. Guessing
 * asserts one tenant's warehouse against another tenant's obligations.
 */
interface PhysicalNexus
{
    public function hasPresenceIn(SubdivisionCode $state, ?NexusSubject $subject = null): bool;
}
