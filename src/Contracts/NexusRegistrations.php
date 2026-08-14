<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\ValueObjects\NexusSubject;

/**
 * Reports which states the seller already holds a tax registration in — so a
 * triggered obligation that has already been handled is reported as
 * {@see NexusStatus::Registered}, not as an outstanding action.
 * Host-owned (e.g. from stored registrations).
 *
 * `$subject` is WHO is being asked about, and is null when the host serves a single
 * seller. An implementation serving several that receives null should answer false
 * rather than infer the seller: of every answer in this package, a wrongly-claimed
 * registration is the one that turns an outstanding obligation into a handled one
 * on someone's dashboard.
 */
interface NexusRegistrations
{
    public function isRegisteredIn(SubdivisionCode $state, ?NexusSubject $subject = null): bool;
}
