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

it('is deny-by-default: no host activity bound means below everywhere', function () {
    // The dataset supplies thresholds, but the seller-activity ledger is empty by
    // default, so nothing is triggered until the host binds real activity.
    expect($this->app->make(NexusEngine::class)->evaluate(new SubdivisionCode('US-CA'))->status)
        ->toBe(NexusStatus::Below);
});
