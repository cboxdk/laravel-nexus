<?php

declare(strict_types=1);

namespace Cbox\Nexus\UsTaxData;

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\KnowsNonTaxingStates;
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
 * guessing — and so does a section that fails {@see verified()}, which is what
 * makes the schemaVersion above a checked claim rather than a comment.
 */
readonly class DatasetNexusThresholdSource implements KnowsNonTaxingStates
{
    private const string CACHE_KEY = 'cbox-nexus:us-dataset:';

    /**
     * The only dataset schema this reader understands. See {@see verified()}.
     *
     * NOTE: `cboxdk/laravel-tax` reads the same publisher's files and carries the
     * same constant and the same verification, deliberately — the two packages do
     * not depend on one another, so the check cannot be shared without inverting a
     * dependency. Both suites assert refusal on a tampered fixture, so a change to
     * one that is not made in the other fails a build rather than going quiet.
     * Raise this constant in BOTH packages or in neither.
     */
    private const int SCHEMA_VERSION = 4;

    public function __construct(
        private Factory $http,
        private Cache $cache,
        private string $location,
        private int $ttl = 86400,
    ) {}

    /**
     * Whether the dataset POSITIVELY states that this state levies no general
     * sales tax.
     *
     * The dataset already encodes the distinction and it would be a waste to throw
     * it away: a no-sales-tax state is PRESENT in the `states` map with an explicit
     * `null`, whereas an unreachable section yields no map at all. Only the first
     * is an answer. A missing key, or a section that could not be read, stays
     * unknown — the direction that fails loudly.
     */
    public function leviesNoSalesTax(SubdivisionCode $state): bool
    {
        $states = $this->section();

        return is_array($states)
            && array_key_exists($state->value, $states)
            && $states[$state->value] === null;
    }

    public function thresholdFor(SubdivisionCode $state, ?DateTimeImmutable $at = null): ?EconomicNexusThreshold
    {
        $states = $this->section();
        $windows = is_array($states) ? ($states[$state->value] ?? null) : null;

        if (! is_array($windows)) {
            return null;
        }

        $window = $this->activeWindow($windows, $at);

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

        // A transaction count that is present but unusable — a string, a float —
        // must not become "this state has no transaction test". Coerced to null it
        // silently turns an OR rule into sales-only, and a seller who crossed on
        // transaction count alone is told they have no obligation.
        if ($transactions !== null && ! is_int($transactions)) {
            return null;
        }
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

        if (! $this->verified($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! is_array($decoded['states'] ?? null)) {
            return null;
        }

        return $decoded['states'];
    }

    /**
     * Whether the fetched nexus section is the one the publisher signed off.
     *
     * The ETL publishes a `manifest.json` carrying a sha256 per section and the
     * `schemaVersion` the files were built to. This reader's own docblock claimed
     * schemaVersion 4 while checking neither, which left two holes with no alarm:
     *
     *  - the default location is a MUTABLE branch head on a third-party host, so one
     *    bad push reaches every deployment within one TTL, with nobody releasing
     *    anything;
     *  - a schemaVersion bump that re-scaled or renamed a field would be read with
     *    this reader's old assumptions. Here that is not a wrong price but a wrong
     *    OBLIGATION: a threshold read too low registers a seller in a state where
     *    they owe nothing, and one read too high leaves them unregistered where
     *    they owe.
     *
     * Verification is REQUIRED over http(s) and optional for a local directory —
     * over the network you did not choose the bytes; on your own disk you did.
     */
    private function verified(string $raw): bool
    {
        $manifest = $this->manifest();

        if ($manifest === null) {
            return ! $this->isRemote();
        }

        if (($manifest['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            return false;
        }

        $files = $manifest['files'] ?? null;
        $sections = is_array($files) ? ($files['sections'] ?? null) : null;
        $entry = is_array($sections) ? ($sections['nexus'] ?? null) : null;
        $expected = is_array($entry) ? ($entry['sha256'] ?? null) : null;

        if (! is_string($expected)) {
            // The manifest exists but says nothing about this section. Remotely that
            // is a gap we cannot close; locally it is the operator's own file.
            return ! $this->isRemote();
        }

        return hash_equals($expected, hash('sha256', $raw));
    }

    /**
     * The publisher's manifest, cached alongside the section. Null when there is
     * none, or it cannot be read or parsed.
     *
     * @return array<array-key, mixed>|null
     */
    private function manifest(): ?array
    {
        $key = self::CACHE_KEY.substr(hash('sha256', $this->location), 0, 16).':manifest';
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $raw = $this->read('manifest.json');

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        $this->cache->put($key, $decoded, $this->ttl);

        return $decoded;
    }

    private function isRemote(): bool
    {
        return str_starts_with($this->location, 'http://')
            || str_starts_with($this->location, 'https://');
    }

    private function read(string $relative): ?string
    {
        $base = rtrim($this->location, '/');

        if ($this->isRemote()) {
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
    private function activeWindow(array $windows, ?DateTimeImmutable $at = null): ?array
    {
        $date = ($at ?? new DateTimeImmutable('today'))->format('Y-m-d');
        foreach ($windows as $window) {
            if (! is_array($window)) {
                continue;
            }

            $from = is_string($window['effectiveFrom'] ?? null) ? $window['effectiveFrom'] : null;
            $to = is_string($window['effectiveTo'] ?? null) ? $window['effectiveTo'] : null;

            if (($from === null || $from <= $date) && ($to === null || $date <= $to)) {
                return $window;
            }
        }

        // NO fallback to the first window. The dated-window mechanism exists to
        // carry law that is pending or repealed, and serving a window the dataset
        // says does not apply defeats it entirely: a state whose only window starts
        // in 2099 would have that threshold applied today, and one whose window has
        // ended would go on triggering registrations under a repealed rule.
        // Refusing leaves the engine at Unknown, which is the truth.
        return null;
    }
}
