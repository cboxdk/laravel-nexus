---
title: Testing
weight: 12
description: Build an engine from in-memory fakes with InteractsWithNexus — the same fakes the package's own suite uses.
---

# Testing

Compose `Cbox\Nexus\Testing\InteractsWithNexus` in your test case for a fully
in-memory engine — no dataset, no HTTP. The package dogfoods these same fakes.

```php
use Cbox\Nexus\Testing\InteractsWithNexus;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Geo\ValueObjects\SubdivisionCode;

$engine = $this->nexusEngine(
    thresholds: ['US-CA' => $this->salesThreshold(500_000)],
    activity:   ['US-CA' => $this->activity(600_000)],
);

expect($engine->evaluate(new SubdivisionCode('US-CA'))->status)
    ->toBe(NexusStatus::Triggered);
```

The fakes — `ArrayNexusThresholdSource`, `ArraySalesLedger`, `ArrayPhysicalNexus`,
`ArrayNexusRegistrations` — are plain array-backed implementations you can also use
outside tests.
