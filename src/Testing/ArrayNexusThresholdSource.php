<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\KnowsNonTaxingStates;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use DateTimeImmutable;

/**
 * An in-memory threshold source for tests — a map of state code to its threshold,
 * so a suite need not stand up the dataset.
 *
 * It implements {@see KnowsNonTaxingStates} so a test can express the distinction
 * the real source draws: a state named in `$noSalesTax` levies none (there is
 * nothing to cross), while a state simply absent from `$thresholds` is unknown.
 * A fake that could not tell those apart would let the conflation back in through
 * the suite.
 */
readonly class ArrayNexusThresholdSource implements KnowsNonTaxingStates
{
    /**
     * @param  array<string, EconomicNexusThreshold>  $thresholds  state code => threshold
     * @param  list<string>  $noSalesTax  state codes that levy no general sales tax
     */
    public function __construct(
        private array $thresholds = [],
        private array $noSalesTax = [],
    ) {}

    public function thresholdFor(SubdivisionCode $state, ?DateTimeImmutable $at = null): ?EconomicNexusThreshold
    {
        return $this->thresholds[$state->value] ?? null;
    }

    public function leviesNoSalesTax(SubdivisionCode $state): bool
    {
        return in_array($state->value, $this->noSalesTax, true);
    }
}
