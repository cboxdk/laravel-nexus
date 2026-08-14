<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use Cbox\Nexus\UsTaxData\DatasetNexusThresholdSource;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

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

// ---- Integrity ------------------------------------------------------------
//
// The default location is a MUTABLE branch head on a third-party host. This
// reader fetched it and believed it — no manifest, no hash, no schema check —
// while its own docblock advertised "schemaVersion 4". The twin reader in
// cboxdk/laravel-tax verified all three. Same publisher, same files, one hardened
// and one not, because a hardening pass touched one repo and not the other.
//
// A wrong threshold here is not a wrong price. It is a wrong OBLIGATION: read too
// low and a seller registers in a state where they owe nothing; too high and they
// stay unregistered where they owe.

/** @param  array<string, mixed>|null  $manifest */
function remoteDataset(string $body, ?array $manifest): DatasetNexusThresholdSource
{
    $responses = ['*/by-section/nexus.json' => Http::response($body)];

    $responses['*/manifest.json'] = $manifest === null
        ? Http::response('', 404)
        : Http::response(json_encode($manifest));

    Http::fake($responses);

    // A fresh cache per call: the reader caches sections and the manifest, and a
    // shared store would let one case answer for the next.
    return new DatasetNexusThresholdSource(
        app(Factory::class),
        new Repository(new ArrayStore),
        'https://data.example.test/us-tax-data',
    );
}

function nexusBody(int $sales = 500_000): string
{
    return json_encode(['states' => ['US-CA' => [
        ['salesUsd' => $sales, 'transactions' => null, 'combinator' => 'sales_only'],
    ]]], JSON_THROW_ON_ERROR);
}

/** @param  array<string, mixed>  $overrides */
function manifestFor(string $body, array $overrides = []): array
{
    return array_replace([
        'schemaVersion' => 4,
        'files' => ['sections' => ['nexus' => ['sha256' => hash('sha256', $body)]]],
    ], $overrides);
}

it('accepts a remote section that matches the publisher manifest', function () {
    $body = nexusBody();

    expect(remoteDataset($body, manifestFor($body))->thresholdFor(new SubdivisionCode('US-CA'))?->salesDollars)
        ->toBe(500_000);
});

it('refuses a remote section whose bytes do not match the manifest hash', function () {
    // The manifest describes the file the publisher signed off; the body is a
    // different one. That is the one-bad-push case, and it must not resolve.
    $tampered = nexusBody(250_000);

    expect(remoteDataset($tampered, manifestFor(nexusBody()))->thresholdFor(new SubdivisionCode('US-CA')))
        ->toBeNull();
});

it('refuses a remote section built to a schema this reader does not understand', function () {
    $body = nexusBody();

    expect(remoteDataset($body, manifestFor($body, ['schemaVersion' => 5]))->thresholdFor(new SubdivisionCode('US-CA')))
        ->toBeNull();
});

it('refuses a remote fetch with no manifest at all, but allows a local one', function () {
    // Over the network you did not choose the bytes. On your own disk you did, so a
    // local mirror without a manifest stays readable — that is a deliberate choice
    // by an operator, not a fetch that went somewhere unexpected.
    expect(remoteDataset(nexusBody(), null)->thresholdFor(new SubdivisionCode('US-CA')))->toBeNull()
        ->and(datasetSource(dirname(__DIR__).'/Fixtures/us-tax-dataset')->thresholdFor(new SubdivisionCode('US-CA')))
        ->not->toBeNull();
});

// ---- Dated windows ----------------------------------------------------------

it('reads the threshold in force on the date asked about', function () {
    // Thresholds are dated law and they move: Kentucky's 200-transaction test was
    // repealed with effect from 2026-08-01. Evaluating every window against today
    // makes a filing prepared in arrears use a threshold that did not exist in the
    // period it covers — a seller who genuinely crossed $100,000 in 2025 is told
    // they were below the $500,000 that replaced it.
    $dir = sys_get_temp_dir().'/nexus-dated-'.bin2hex(random_bytes(5)).'/by-section';
    mkdir($dir, 0o755, true);
    file_put_contents($dir.'/nexus.json', json_encode(['states' => [
        'US-XX' => [
            ['salesUsd' => 100000, 'transactions' => null, 'combinator' => 'sales_only', 'effectiveFrom' => null, 'effectiveTo' => '2025-12-31'],
            ['salesUsd' => 500000, 'transactions' => null, 'combinator' => 'sales_only', 'effectiveFrom' => '2026-01-01', 'effectiveTo' => null],
        ],
    ]]));

    $source = datasetSource(dirname($dir));

    expect($source->thresholdFor(new SubdivisionCode('US-XX'), new DateTimeImmutable('2025-06-15'))?->salesDollars)->toBe(100_000)
        ->and($source->thresholdFor(new SubdivisionCode('US-XX'), new DateTimeImmutable('2026-06-15'))?->salesDollars)->toBe(500_000);
});

it('refuses a threshold whose only window has not started yet', function () {
    // The dated-window mechanism exists to carry law that is PENDING or repealed.
    // Serving the first window regardless defeated it: a state whose only window
    // begins in 2099 had that threshold applied today, and one whose window had
    // ended went on triggering registrations under a repealed rule.
    $dir = sys_get_temp_dir().'/nexus-future-'.bin2hex(random_bytes(5)).'/by-section';
    mkdir($dir, 0o755, true);
    file_put_contents($dir.'/nexus.json', json_encode(['states' => [
        'US-XX' => [['salesUsd' => 250000, 'transactions' => null, 'combinator' => 'sales_only', 'effectiveFrom' => '2099-01-01', 'effectiveTo' => null]],
    ]]));

    expect(datasetSource(dirname($dir))->thresholdFor(new SubdivisionCode('US-XX')))->toBeNull();
});

it('refuses a transaction count it cannot read rather than dropping the test', function () {
    // A count present but unusable — a string, a float — must not become "this
    // state has no transaction test". Coerced to null it silently turns an OR rule
    // into sales-only, and a seller who crossed on transaction count alone is told
    // they have no obligation at all.
    $dir = sys_get_temp_dir().'/nexus-bad-'.bin2hex(random_bytes(5)).'/by-section';
    mkdir($dir, 0o755, true);
    file_put_contents($dir.'/nexus.json', json_encode(['states' => [
        'US-XX' => [['salesUsd' => 100000, 'transactions' => '200', 'combinator' => 'sales_or_transactions', 'effectiveFrom' => null, 'effectiveTo' => null]],
    ]]));

    expect(datasetSource(dirname($dir))->thresholdFor(new SubdivisionCode('US-XX')))->toBeNull();
});
