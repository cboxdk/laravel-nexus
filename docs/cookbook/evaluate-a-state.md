---
title: Evaluate one state
weight: 31
description: Resolve a single state's nexus status and act on it.
---

# Evaluate one state

```php
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Geo\ValueObjects\SubdivisionCode;

$e = app(NexusEngine::class)->evaluate(new SubdivisionCode('US-TX'));

if ($e->needsAction()) {
    // register in $e->state; $e->reason explains why, $e->threshold->describe() the trigger
}

$e->progress; // e.g. 0.92 — 92% toward the threshold
```

`evaluate()` never throws for an unknown state — it returns `Below` with a reason
("No economic-nexus threshold known…"), so a caller can rely on a status always
coming back.
