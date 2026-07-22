<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusStatus;

/**
 * Reports which states the seller already holds a tax registration in — so a
 * triggered obligation that has already been handled is reported as
 * {@see NexusStatus::Registered}, not as an outstanding action.
 * Host-owned (e.g. from stored registrations).
 */
interface NexusRegistrations
{
    public function isRegisteredIn(SubdivisionCode $state): bool;
}
