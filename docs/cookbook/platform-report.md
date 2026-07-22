---
title: Build a platform report
weight: 32
description: Roll every state a seller sells into up into triggered / approaching / registered buckets.
---

# Build a platform report

```php
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Geo\ValueObjects\SubdivisionCode;

$states = array_map(fn (string $c) => new SubdivisionCode($c), $sellerSellsInto);

$report = app(NexusEngine::class)->report($states);

$report->triggered();    // list<NexusEvaluation> — act now
$report->approaching();  // watch
$report->registered();   // already handled
$report->forState('US-CA');
```

Feed `$report->triggered()` into your platform's alerts/onboarding, and
`approaching()` into a watchlist so a seller sees a threshold coming before they
cross it.
