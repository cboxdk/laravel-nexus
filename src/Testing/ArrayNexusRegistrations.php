<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\NexusRegistrations;

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

    public function isRegisteredIn(SubdivisionCode $state): bool
    {
        return in_array($state->value, $this->states, true);
    }
}
