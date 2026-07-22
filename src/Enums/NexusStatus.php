<?php

declare(strict_types=1);

namespace Cbox\Nexus\Enums;

/**
 * A seller's economic-nexus standing in one state — the answer the engine
 * produces per jurisdiction.
 */
enum NexusStatus: string
{
    /** Below the threshold and not close to it: no obligation on this activity. */
    case Below = 'below';

    /** Within the configured warning band of the threshold — watch this state. */
    case Approaching = 'approaching';

    /** Threshold crossed (or physical presence exists) but the seller is NOT yet
     *  registered: a registration obligation has likely been triggered. */
    case Triggered = 'triggered';

    /** The seller already holds a registration in the state — obligation handled. */
    case Registered = 'registered';

    /** Whether this status means the seller should act (register / verify). */
    public function needsAction(): bool
    {
        return $this === self::Triggered;
    }
}
