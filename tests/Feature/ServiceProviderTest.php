<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Nexus\Contracts\NexusThresholdSource;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\UsTaxData\DatasetNexusThresholdSource;

it('binds the engine and a dataset-backed threshold source by default', function () {
    expect($this->app->make(NexusThresholdSource::class))->toBeInstanceOf(DatasetNexusThresholdSource::class)
        ->and($this->app->make(NexusEngine::class))->toBeInstanceOf(NexusEngine::class);
});

it('resolves a threshold from the bound dataset source (fixture)', function () {
    expect($this->app->make(NexusThresholdSource::class)->thresholdFor(new SubdivisionCode('US-TX'))?->salesDollars)
        ->toBe(500_000);
});

it('is deny-by-default: no host ledger bound means unknown, not a clean board', function () {
    // The dataset supplies thresholds, but the shipped ledger is empty — it has no
    // invoices and cannot answer for any state. Reporting `Below` there would show
    // a fresh install fifty green states while the seller crossed thresholds
    // nationwide, so the engine reports Unknown until a real ledger is bound.
    $evaluation = $this->app->make(NexusEngine::class)->evaluate(new SubdivisionCode('US-CA'));

    expect($evaluation->status)->toBe(NexusStatus::Unknown)
        ->and($evaluation->needsAction())->toBeTrue();
});

it('surfaces every unevaluable state in the report rather than burying them', function () {
    $report = $this->app->make(NexusEngine::class)->report([
        new SubdivisionCode('US-CA'),
        new SubdivisionCode('US-TX'),
        new SubdivisionCode('US-NY'),
    ]);

    // The bucket exists precisely so a dashboard built from triggered/approaching/
    // registered cannot render an empty, reassuring board.
    expect($report->unknown())->toHaveCount(3)
        ->and($report->triggered())->toBe([])
        ->and($report->registered())->toBe([]);
});
