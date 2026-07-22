<?php

declare(strict_types=1);

use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;

it('tests crossing per combinator', function () {
    $salesOnly = new EconomicNexusThreshold(100_000, null, NexusCombinator::SalesOnly);
    $or = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesOrTransactions);
    $and = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesAndTransactions);

    expect($salesOnly->isMet(100_000, 0))->toBeTrue()
        ->and($salesOnly->isMet(99_999, 9_999))->toBeFalse()
        ->and($or->isMet(5_000, 200))->toBeTrue()       // transactions alone
        ->and($or->isMet(5_000, 10))->toBeFalse()
        ->and($and->isMet(150_000, 10))->toBeFalse()     // needs both
        ->and($and->isMet(150_000, 250))->toBeTrue();
});

it('computes progress as the nearer measure for OR and the further for AND', function () {
    $or = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesOrTransactions);
    $and = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesAndTransactions);

    // sales 50% ($50k), transactions 90% (180). OR -> max = 0.9; AND -> min = 0.5.
    expect($or->progress(50_000, 180))->toBe(0.9)
        ->and($and->progress(50_000, 180))->toBe(0.5);
});

it('describes the threshold for display', function () {
    expect((new EconomicNexusThreshold(500_000, null, NexusCombinator::SalesOnly))->describe())->toBe('$500,000')
        ->and((new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesOrTransactions))->describe())->toBe('$100,000 or 200 transactions')
        ->and((new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesAndTransactions))->describe())->toBe('$100,000 and 200 transactions');
});
