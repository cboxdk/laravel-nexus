---
title: Quickstart
weight: 2
description: From install to a per-state nexus report in one read.
---

# Quickstart

```bash
composer require cboxdk/laravel-nexus
```

Thresholds come from the `us-tax-data` dataset out of the box. Bind a
`SalesLedger` so the engine knows the seller's cumulative sales per state:

```php
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\ValueObjects\SellerActivity;
use Cbox\Geo\ValueObjects\SubdivisionCode;

app()->singleton(SalesLedger::class, fn () => new class implements SalesLedger {
    public function activityFor(SubdivisionCode $state): ?SellerActivity
    {
        // cumulative sales$/transactions into $state over its measuring period
        return new SellerActivity(salesDollars: 620_000, transactions: 900);
    }
});
```

Then evaluate:

```php
use Cbox\Nexus\Contracts\NexusEngine;

$evaluation = app(NexusEngine::class)->evaluate(new SubdivisionCode('US-CA'));

$evaluation->status;      // NexusStatus::Triggered
$evaluation->needsAction(); // true
$evaluation->reason;      // "Economic nexus met in US-CA ($500,000) — register."
```

Roll several states up for a dashboard with `->report([...])` and its
`triggered()` / `approaching()` / `registered()` buckets.
