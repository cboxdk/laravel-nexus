<?php

declare(strict_types=1);

namespace Cbox\Nexus\Tests\Fixtures;

use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Nexus\Testing\InteractsWithNexus;

/**
 * A PHPStan-visible composition site for {@see InteractsWithNexus} (Pest test
 * closures are not analysed), so the trait and its helpers are type-checked.
 */
class NexusFixture
{
    use InteractsWithNexus;

    public function build(): NexusEngine
    {
        return $this->nexusEngine(
            thresholds: ['US-CA' => $this->salesThreshold(500_000)],
            activity: ['US-CA' => $this->activity(100_000, 5)],
            physical: ['US-FL'],
            registered: ['US-NY'],
        );
    }
}
