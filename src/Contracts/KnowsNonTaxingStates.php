<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusStatus;

/**
 * A {@see NexusThresholdSource} that can tell "this state levies no general sales
 * tax" apart from "I could not resolve a threshold".
 *
 * Both come back from `thresholdFor()` as `null`, and without this the engine must
 * treat both as {@see NexusStatus::Unknown} — so Delaware, Montana, New Hampshire
 * and Oregon appear as four permanent action items on every healthy install,
 * telling the operator to go check their network. That is worse than cosmetic: it
 * dilutes the one signal that exists to make a genuinely unreachable dataset
 * visible, and a signal that cries wolf four times a day stops being read.
 *
 * A SEPARATE contract rather than a second method on {@see NexusThresholdSource},
 * following the same reasoning as the rate sources: a host binding a simple
 * threshold lookup should not have to answer a question it has no data for, and
 * every existing implementation keeps working untouched.
 */
interface KnowsNonTaxingStates extends NexusThresholdSource
{
    /**
     * Whether the state levies no general sales tax at all, so there is no
     * threshold to cross and no registration to make.
     *
     * Must return false — not true — when the source cannot tell. A wrong "no tax
     * here" is silent under-collection; a wrong "unknown" is a visible alarm.
     */
    public function leviesNoSalesTax(SubdivisionCode $state): bool;
}
