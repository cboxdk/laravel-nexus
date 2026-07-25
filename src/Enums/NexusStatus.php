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

    /**
     * The threshold for this state could not be resolved, so no standing can be stated.
     *
     * Distinct from {@see Below} on purpose. Reporting "below" when the engine simply does not
     * KNOW the threshold reads as compliance, and it is the failure mode a self-hosted deployment
     * actually hits: the threshold dataset is fetched over the network, so a firewalled install
     * silently showed every state as below and under-collected sales tax with no signal at all.
     * "I don't know" and "you're fine" are different answers and must not share a case.
     */
    case Unknown = 'unknown';

    /** Whether this status means the seller should act (register / verify). */
    public function needsAction(): bool
    {
        // Unknown needs action too — not a registration, but an operator has to resolve why the
        // threshold is unavailable. Treating it as inert is what let the gap stay invisible.
        return $this === self::Triggered || $this === self::Unknown;
    }
}
