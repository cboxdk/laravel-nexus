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
 */
readonly class NexusEvaluation
{
    public function __construct(
        public SubdivisionCode $state,
        public NexusStatus $status,
        public ?EconomicNexusThreshold $threshold,
        public ?SellerActivity $activity,
        /** 0.0–1.0+ toward the threshold; null when no threshold/activity is known. */
        public ?float $progress,
        public bool $physicalPresence,
        public string $reason,
    ) {}

    /** Whether the seller should act on this state (register / verify). */
    public function needsAction(): bool
    {
        return $this->status->needsAction();
    }
}
