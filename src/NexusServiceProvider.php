<?php

declare(strict_types=1);

namespace Cbox\Nexus;

use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Nexus\Contracts\NexusRegistrations;
use Cbox\Nexus\Contracts\NexusThresholdSource;
use Cbox\Nexus\Contracts\PhysicalNexus;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\Engine\DefaultNexusEngine;
use Cbox\Nexus\Testing\ArrayNexusRegistrations;
use Cbox\Nexus\Testing\ArrayPhysicalNexus;
use Cbox\Nexus\Testing\ArraySalesLedger;
use Cbox\Nexus\UsTaxData\DatasetNexusThresholdSource;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

/**
 * Package entry point. Binds the engine and its four data seams behind their
 * contracts. Thresholds default to the us-tax-data dataset; the seller's activity,
 * physical presence and registrations are HOST data, so they bind to empty
 * defaults (deny-by-default: no data → nothing claimed) until the host rebinds
 * them against its own billing/registration records.
 */
class NexusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nexus.php', 'nexus');

        $this->app->singleton(NexusThresholdSource::class, static function (Application $app): NexusThresholdSource {
            $config = $app->make(Config::class);
            $location = $config->get('nexus.us_tax_data.location');
            $ttl = $config->get('nexus.us_tax_data.ttl');

            return new DatasetNexusThresholdSource(
                $app->make(Factory::class),
                $app->make(Cache::class),
                is_string($location) ? $location : '',
                is_int($ttl) ? $ttl : 86400,
            );
        });

        // Host-owned data seams — empty by default, rebind against real records.
        $this->app->singleton(SalesLedger::class, static fn (): SalesLedger => new ArraySalesLedger);
        $this->app->singleton(PhysicalNexus::class, static fn (): PhysicalNexus => new ArrayPhysicalNexus);
        $this->app->singleton(NexusRegistrations::class, static fn (): NexusRegistrations => new ArrayNexusRegistrations);

        $this->app->singleton(NexusEngine::class, static function (Application $app): NexusEngine {
            $ratio = $app->make(Config::class)->get('nexus.approaching_ratio');

            return new DefaultNexusEngine(
                $app->make(NexusThresholdSource::class),
                $app->make(SalesLedger::class),
                $app->make(PhysicalNexus::class),
                $app->make(NexusRegistrations::class),
                is_numeric($ratio) ? (float) $ratio : 0.8,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nexus.php' => $this->app->configPath('nexus.php'),
            ], 'nexus-config');
        }
    }
}
