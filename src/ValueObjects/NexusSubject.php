<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use Cbox\Nexus\Contracts\SalesLedger;
use InvalidArgumentException;

/**
 * WHO a nexus question is being asked about.
 *
 * A single-tenant host has one seller and never needs this — its ledger answers
 * for the only seller there is. A hosted service has many, and every seam this
 * package exposes ({@see SalesLedger} and its siblings) is a place where the wrong
 * seller's totals could be returned for the right state.
 *
 * The seams used to carry only a state, so a multi-tenant implementation had to
 * infer the seller from ambient context — the request, a container-scoped
 * singleton. That works until it doesn't: a queued job or a long-lived worker
 * carries the context of whoever was last served, and the failure is silent and
 * cross-tenant. Passing the subject explicitly makes the seller part of the
 * question rather than part of the environment.
 *
 * The key is opaque to this package — a tenant id, an account uuid, whatever the
 * host keys its own data by. It is only ever handed back to the host's own
 * implementations.
 */
readonly class NexusSubject
{
    public function __construct(public string $key)
    {
        if (trim($key) === '') {
            // An empty key is the shape an unset tenant id arrives in, and it would
            // otherwise read as a perfectly valid subject that matches nothing —
            // or worse, matches a row keyed on the empty string.
            throw new InvalidArgumentException('A nexus subject key cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }
}
