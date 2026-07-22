<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;

function state(string $code): SubdivisionCode
{
    return new SubdivisionCode($code);
}

it('is below when cumulative sales are under the threshold', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-CA' => $this->salesThreshold(500_000)],
        activity: ['US-CA' => $this->activity(100_000)],
    );

    $e = $engine->evaluate(state('US-CA'));

    expect($e->status)->toBe(NexusStatus::Below)
        ->and($e->needsAction())->toBeFalse()
        ->and($e->progress)->toBe(0.2);
});

it('is approaching within the warning band', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-TX' => $this->salesThreshold(100_000)],
        activity: ['US-TX' => $this->activity(85_000)],
        approachingRatio: 0.8,
    );

    expect($engine->evaluate(state('US-TX'))->status)->toBe(NexusStatus::Approaching);
});

it('is triggered when the economic threshold is crossed', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-TX' => $this->salesThreshold(100_000)],
        activity: ['US-TX' => $this->activity(150_000)],
    );

    $e = $engine->evaluate(state('US-TX'));

    expect($e->status)->toBe(NexusStatus::Triggered)
        ->and($e->needsAction())->toBeTrue();
});

it('is triggered by physical presence regardless of sales', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-FL' => $this->salesThreshold(100_000)],
        activity: ['US-FL' => $this->activity(1_000)],
        physical: ['US-FL'],
    );

    $e = $engine->evaluate(state('US-FL'));

    expect($e->status)->toBe(NexusStatus::Triggered)
        ->and($e->physicalPresence)->toBeTrue();
});

it('reports registered when the seller already holds a registration', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-NY' => $this->salesThreshold(500_000)],
        activity: ['US-NY' => $this->activity(600_000)], // would otherwise trigger
        registered: ['US-NY'],
    );

    expect($engine->evaluate(state('US-NY'))->status)->toBe(NexusStatus::Registered);
});

it('denies by default with no threshold or no activity', function () {
    $noThreshold = $this->nexusEngine(activity: ['US-CA' => $this->activity(9_000_000)]);
    $noActivity = $this->nexusEngine(thresholds: ['US-CA' => $this->salesThreshold(100_000)]);

    expect($noThreshold->evaluate(state('US-CA'))->status)->toBe(NexusStatus::Below)
        ->and($noActivity->evaluate(state('US-CA'))->status)->toBe(NexusStatus::Below);
});

it('honours the combinator for sales-or-transactions and sales-and-transactions', function () {
    $or = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesOrTransactions);
    $and = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesAndTransactions);

    $engine = $this->nexusEngine(
        thresholds: ['US-KY' => $or, 'US-CT' => $and],
        activity: ['US-KY' => $this->activity(5_000, 250), 'US-CT' => $this->activity(5_000, 250)],
    );

    // OR: 250 transactions alone triggers. AND: needs BOTH — $5k sales fails.
    expect($engine->evaluate(state('US-KY'))->status)->toBe(NexusStatus::Triggered)
        ->and($engine->evaluate(state('US-CT'))->status)->toBe(NexusStatus::Below);
});

it('rolls a report up across states', function () {
    $engine = $this->nexusEngine(
        thresholds: [
            'US-CA' => $this->salesThreshold(500_000),
            'US-TX' => $this->salesThreshold(500_000),
            'US-NY' => $this->salesThreshold(500_000),
        ],
        activity: [
            'US-CA' => $this->activity(600_000), // triggered
            'US-TX' => $this->activity(450_000), // approaching (0.9)
            'US-NY' => $this->activity(600_000), // registered
        ],
        registered: ['US-NY'],
    );

    $report = $engine->report([state('US-CA'), state('US-TX'), state('US-NY')]);

    expect($report->triggered())->toHaveCount(1)
        ->and($report->approaching())->toHaveCount(1)
        ->and($report->registered())->toHaveCount(1)
        ->and($report->forState('US-CA')?->status)->toBe(NexusStatus::Triggered);
});
