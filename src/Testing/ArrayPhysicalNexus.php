<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\PhysicalNexus;

/**
 * An in-memory {@see PhysicalNexus} — the states the seller has asserted physical
 * presence in. Empty by default (deny-by-default: no presence claimed).
 */
readonly class ArrayPhysicalNexus implements PhysicalNexus
{
    /** @var list<string> */
    private array $states;

    /**
     * @param  list<string>  $states  state codes with physical presence
     */
    public function __construct(array $states = [])
    {
        $this->states = $states;
    }

    public function hasPresenceIn(SubdivisionCode $state): bool
    {
        return in_array($state->value, $this->states, true);
    }
}
