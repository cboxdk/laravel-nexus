<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\NexusRegistrations;
use Cbox\Nexus\ValueObjects\NexusSubject;

/**
 * An in-memory {@see NexusRegistrations} — the states the seller already holds a
 * registration in. Empty by default.
 */
readonly class ArrayNexusRegistrations implements NexusRegistrations
{
    /** @var list<string> */
    private array $states;

    /**
     * @param  list<string>  $states  state codes with an active registration
     */
    public function __construct(array $states = [])
    {
        $this->states = $states;
    }

    /** Holds one seller's registrations, so the subject is accepted and ignored. */
    public function isRegisteredIn(SubdivisionCode $state, ?NexusSubject $subject = null): bool
    {
        return in_array($state->value, $this->states, true);
    }
}
