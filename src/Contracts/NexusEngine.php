<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\ValueObjects\NexusEvaluation;
use Cbox\Nexus\ValueObjects\NexusReport;
use Cbox\Nexus\ValueObjects\NexusSubject;
use DateTimeImmutable;

/**
 * Evaluates a seller's economic-nexus standing. It owns the DECISION logic
 * (registered vs physical vs economic crossing vs approaching vs below); the DATA
 * — thresholds, activity, physical presence, registrations — is sourced behind the
 * other contracts. It never accumulates sales itself and never infers nexus from a
 * single supply; it compares host-supplied cumulative totals to sourced thresholds.
 */
interface NexusEngine
{
    /**
     * `$subject` names WHO is being evaluated and is carried into every host-owned
     * seam; it is null when the host serves a single seller. `$asOf` moves the
     * question to a date other than today — a filing prepared in arrears asks about
     * the period it covers, not about now.
     */
    public function evaluate(
        SubdivisionCode $state,
        ?NexusSubject $subject = null,
        ?DateTimeImmutable $asOf = null,
    ): NexusEvaluation;

    /**
     * @param  list<SubdivisionCode>  $states
     */
    public function report(
        array $states,
        ?NexusSubject $subject = null,
        ?DateTimeImmutable $asOf = null,
    ): NexusReport;
}
