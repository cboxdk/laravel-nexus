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
use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\SellerActivity;

app()->singleton(SalesLedger::class, fn () => new class implements SalesLedger {
    public function activityFor(ActivityQuery $query): ?SellerActivity
    {
        // The query already carries the state's own terms, so you do not have to
        // look them up: WHAT to count ($query->basis), and over WHICH dates
        // ($query->windows() — usually one range, two where a state measures the
        // previous OR current calendar year).
        $window = $query->windows()[0] ?? null;

        return new SellerActivity(
            salesDollars: 620_000,
            transactions: 900,
            periodStart: $window?->from,
            periodEnd: $window?->to,
            basis: $query->basis,   // declare what you actually counted
            period: $query->period,
        );
    }
});
```

Declaring `basis` and `period` is worth the two lines: the engine cannot recompute
your totals, but it can refuse when you counted something other than what the state
measures — and it will say `Unknown` rather than give a confident answer to a
question the state never asked.

`periodStart` and `periodEnd` are worth two more, and they catch a different
mistake. `period` says which RULE you followed; the dates say which twelve months
you actually summed. Those come apart in ordinary ways — a total served from a cache
warmed last quarter, an as-of date computed once at boot — and the declared rule
matches in every one of them. Given the dates, the engine compares them against the
ranges the state's rule resolves to, and refuses totals accumulated over some other
window.

It refuses only where the dates genuinely cannot settle the question. A shorter
range that has already crossed the threshold is accepted: the required window
contains it, so its total can only be larger. A longer range that is still below is
accepted for the mirror reason. Leave the dates null and the verdict stands, with a
caveat saying the window was never verified.

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
