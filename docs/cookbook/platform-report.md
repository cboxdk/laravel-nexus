---
title: Build a platform report
weight: 32
description: Roll every state a seller sells into up into action / watch / handled buckets — without silently dropping the states the engine could not evaluate.
---

# Build a platform report

```php
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Geo\ValueObjects\SubdivisionCode;

$states = array_map(fn (string $c) => new SubdivisionCode($c), $sellerSellsInto);

$report = app(NexusEngine::class)->report($states);

$report->needingAction();  // list<NexusEvaluation> — crossed obligations AND unevaluable states
$report->approaching();    // watch
$report->registered();     // already handled
$report->notApplicable();  // DE, MT, NH, OR — nothing to comply with
$report->forState('US-CA');
```

Feed `needingAction()` into your platform's alerts and onboarding, and
`approaching()` into a watchlist so a seller sees a threshold coming before they
cross it.

## Do not build the board from `triggered()` alone

`needingAction()` exists because the obvious dashboard — render `triggered()`,
`approaching()`, `registered()`, ship — has a failure mode that looks like
success. If the threshold dataset is unreachable, or no `SalesLedger` is bound,
every state resolves to `Unknown`. All three of those buckets come back empty, the
board is green, and the seller under-collects nationwide.

That is the exact failure `NexusStatus::Unknown` was added to make visible, and a
consumer that never asks for `unknown()` puts it straight back. One call that
already includes them is harder to get wrong than four you have to remember.

```php
// Surface WHY, not just how many — the reason names the seam that came up empty.
foreach ($report->needingAction() as $evaluation) {
    if ($evaluation->status === NexusStatus::Unknown) {
        $this->alertOperator($evaluation->state, $evaluation->reason);
    }
}
```

Verdicts may also carry `caveats` — things the engine relied on but could not
verify, such as a ledger that never declared which sales it counted. Show them
next to the status; see [the decision model](../core-concepts/nexus-status.md).
