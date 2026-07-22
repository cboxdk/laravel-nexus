<?php

declare(strict_types=1);

namespace Cbox\Nexus\Tests;

use Cbox\Nexus\NexusServiceProvider;
use Cbox\Nexus\Testing\InteractsWithNexus;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithNexus;

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [NexusServiceProvider::class];
    }

    /** Point the dataset at the committed fixture so the suite runs offline. */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('nexus.us_tax_data.location', __DIR__.'/Fixtures/us-tax-dataset');
    }
}
