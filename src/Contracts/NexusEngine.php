<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\ValueObjects\NexusEvaluation;
use Cbox\Nexus\ValueObjects\NexusReport;

/**
 * Evaluates a seller's economic-nexus standing. It owns the DECISION logic
 * (registered vs physical vs economic crossing vs approaching vs below); the DATA
 * — thresholds, activity, physical presence, registrations — is sourced behind the
 * other contracts. It never accumulates sales itself and never infers nexus from a
 * single supply; it compares host-supplied cumulative totals to sourced thresholds.
 */
interface NexusEngine
{
    public function evaluate(SubdivisionCode $state): NexusEvaluation;

    /**
     * @param  list<SubdivisionCode>  $states
     */
    public function report(array $states): NexusReport;
}
