<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusStatus;

/**
 * The engine's verdict for one state: the {@see NexusStatus}, the threshold and
 * activity it was computed from, how far along the seller is toward crossing, and
 * whether physical presence forced the outcome — with a human-readable reason.
 * Deliberately typed, not an array bag, so consumers branch on real values.
 *
 * `caveats` records what the verdict rests on that the engine could NOT check —
 * chiefly a host that fed totals without declaring which sales it counted or over
 * what window. A verdict with caveats is still the best answer available, but it
 * is not the same as one the engine could verify end to end, and a dashboard that
 * shows the status without them overstates the certainty.
 */
readonly class NexusEvaluation
{
    /**
     * @param  list<string>  $caveats  Unverifiable assumptions behind this verdict.
     */
    public function __construct(
        public SubdivisionCode $state,
        public NexusStatus $status,
        public ?EconomicNexusThreshold $threshold,
        public ?SellerActivity $activity,
        /** 0.0–1.0+ toward the threshold; null when no threshold/activity is known. */
        public ?float $progress,
        public bool $physicalPresence,
        public string $reason,
        public array $caveats = [],
    ) {}

    /** Whether the seller should act on this state (register / verify). */
    public function needsAction(): bool
    {
        return $this->status->needsAction();
    }

    /** Whether the verdict rests on something the engine could not verify. */
    public function isQualified(): bool
    {
        return $this->caveats !== [];
    }
}
