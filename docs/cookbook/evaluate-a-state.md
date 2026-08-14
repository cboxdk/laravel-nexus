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

`evaluate()` never throws — a status always comes back. But note what it returns
when it *cannot answer*: **`NexusStatus::Unknown`, not `Below`**, with a reason
naming the seam that came up empty (an unreachable threshold dataset, a ledger
that cannot answer, or totals counted on a different basis than the state
measures).

`Unknown` returns `needsAction() === true`. Branch on the status rather than
treating any non-`Triggered` result as a clean bill of health:

```php
use Cbox\Nexus\Enums\NexusStatus;

match ($e->status) {
    NexusStatus::Triggered     => $this->startRegistration($e),
    NexusStatus::Unknown       => $this->alertOperator($e->reason),  // a fault, not a verdict
    NexusStatus::NotApplicable => null,                              // DE/MT/NH/OR — nothing to do
    default                    => null,
};
```

A verdict may also be *qualified* — `$e->isQualified()` is true when the engine
could not verify something it relied on, with the details in `$e->caveats`. See
[the decision model](../core-concepts/nexus-status.md).
