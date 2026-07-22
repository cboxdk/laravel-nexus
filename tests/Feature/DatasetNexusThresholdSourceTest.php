<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use Cbox\Nexus\UsTaxData\DatasetNexusThresholdSource;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory;

function datasetSource(string $location): DatasetNexusThresholdSource
{
    return new DatasetNexusThresholdSource(
        app(Factory::class),
        app(Cache::class),
        $location,
    );
}

it('reads a state threshold with its full measurement richness from the dataset', function () {
    $source = datasetSource(dirname(__DIR__).'/Fixtures/us-tax-dataset');

    $ca = $source->thresholdFor(new SubdivisionCode('US-CA'));

    expect($ca)->not->toBeNull()
        ->and($ca->salesDollars)->toBe(500_000)
        ->and($ca->transactions)->toBeNull()
        ->and($ca->combinator)->toBe(NexusCombinator::SalesOnly)
        ->and($ca->measuringPeriod)->toBe(NexusMeasurementPeriod::PreviousOrCurrentCalendarYear)
        ->and($ca->salesBasis)->toBe(NexusSalesBasis::GrossSales);
});

it('returns null for a no-sales-tax state and an unreadable location', function () {
    $source = datasetSource(dirname(__DIR__).'/Fixtures/us-tax-dataset');

    expect($source->thresholdFor(new SubdivisionCode('US-OR')))->toBeNull()
        ->and(datasetSource('/no/such/dir')->thresholdFor(new SubdivisionCode('US-CA')))->toBeNull();
});

it('selects the window in effect now over a future-dated one', function () {
    $dir = sys_get_temp_dir().'/nexus-ds-'.bin2hex(random_bytes(5)).'/by-section';
    mkdir($dir, 0o755, true);
    file_put_contents($dir.'/nexus.json', json_encode(['states' => [
        'US-XX' => [
            ['salesUsd' => 500000, 'transactions' => null, 'combinator' => 'sales_only', 'effectiveFrom' => null, 'effectiveTo' => null],
            ['salesUsd' => 250000, 'transactions' => null, 'combinator' => 'sales_only', 'effectiveFrom' => '2099-01-01', 'effectiveTo' => null],
        ],
    ]]));

    expect(datasetSource(dirname($dir))->thresholdFor(new SubdivisionCode('US-XX'))?->salesDollars)->toBe(500_000);
});
