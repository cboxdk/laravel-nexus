<?php

declare(strict_types=1);

namespace Cbox\Nexus\UsTaxData;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\NexusThresholdSource;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory;
use Throwable;

/**
 * Economic-nexus thresholds sourced from the compiled us-tax-data dataset
 * (schemaVersion 4). It reads only the small `by-section/nexus.json` — a dated
 * list of windows per state — and returns the window in effect now, carrying the
 * full richness the dataset now holds: the sales/transaction figures AND the
 * measurement period, sales basis, and marketplace treatment.
 *
 * The location is config-driven (`nexus.us_tax_data.location`): an http(s) base URL
 * (the public dataset mirror) or a local directory. A URL is fetched and cached;
 * any transport/read/parse failure yields null so the engine denies rather than
 * guessing.
 */
readonly class DatasetNexusThresholdSource implements NexusThresholdSource
{
    private const string CACHE_KEY = 'cbox-nexus:us-dataset:';

    public function __construct(
        private Factory $http,
        private Cache $cache,
        private string $location,
        private int $ttl = 86400,
    ) {}

    public function thresholdFor(SubdivisionCode $state): ?EconomicNexusThreshold
    {
        $states = $this->section();
        $windows = is_array($states) ? ($states[$state->value] ?? null) : null;

        if (! is_array($windows)) {
            return null;
        }

        $window = $this->activeWindow($windows);

        if ($window === null) {
            return null;
        }

        $sales = $window['salesUsd'] ?? null;
        $combinator = $window['combinator'] ?? null;

        if (! is_int($sales) || ! is_string($combinator)) {
            return null;
        }

        $combinatorEnum = NexusCombinator::tryFrom($combinator);

        if ($combinatorEnum === null) {
            return null;
        }

        $transactions = $window['transactions'] ?? null;
        $period = $window['measuringPeriod'] ?? null;
        $basis = $window['salesBasis'] ?? null;
        $marketplace = $window['includesMarketplaceSales'] ?? null;

        return new EconomicNexusThreshold(
            $sales,
            is_int($transactions) ? $transactions : null,
            $combinatorEnum,
            is_string($period) ? NexusMeasurementPeriod::tryFrom($period) : null,
            is_string($basis) ? NexusSalesBasis::tryFrom($basis) : null,
            is_bool($marketplace) ? $marketplace : null,
        );
    }

    /**
     * The nexus section's `states` map, from cache or freshly loaded. Null on any
     * failure.
     *
     * @return array<array-key, mixed>|null
     */
    private function section(): ?array
    {
        $key = self::CACHE_KEY.substr(hash('sha256', $this->location), 0, 16);

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $states = $this->fetchStates();

        if ($states !== null) {
            $this->cache->put($key, $states, $this->ttl);
        }

        return $states;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function fetchStates(): ?array
    {
        $raw = $this->read('by-section/nexus.json');

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! is_array($decoded['states'] ?? null)) {
            return null;
        }

        return $decoded['states'];
    }

    private function read(string $relative): ?string
    {
        $base = rtrim($this->location, '/');

        if (str_starts_with($this->location, 'http://') || str_starts_with($this->location, 'https://')) {
            try {
                $response = $this->http->acceptJson()->get($base.'/'.$relative);
            } catch (Throwable) {
                return null;
            }

            return $response->successful() ? $response->body() : null;
        }

        $path = $base.'/'.$relative;

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        return $raw === false ? null : $raw;
    }

    /**
     * The dated window in effect today from a list, else the first.
     *
     * @param  array<array-key, mixed>  $windows
     * @return array<array-key, mixed>|null
     */
    private function activeWindow(array $windows): ?array
    {
        $date = (new DateTimeImmutable('today'))->format('Y-m-d');
        $fallback = null;

        foreach ($windows as $window) {
            if (! is_array($window)) {
                continue;
            }

            $fallback ??= $window;

            $from = is_string($window['effectiveFrom'] ?? null) ? $window['effectiveFrom'] : null;
            $to = is_string($window['effectiveTo'] ?? null) ? $window['effectiveTo'] : null;

            if (($from === null || $from <= $date) && ($to === null || $date <= $to)) {
                return $window;
            }
        }

        return $fallback;
    }
}
