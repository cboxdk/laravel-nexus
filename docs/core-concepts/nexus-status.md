---
title: The decision model
weight: 22
description: How each state resolves to Registered, Triggered, Unknown, Below, Approaching or Not applicable — in precedence order, and why "I don't know" is its own answer.
---

# The decision model

For a state, `DefaultNexusEngine::evaluate()` resolves a `NexusStatus` in this
precedence:

1. **Registered** — the seller already holds a registration there (obligation
   handled).
2. **Triggered (physical)** — asserted physical presence establishes nexus on its
   own, whatever the sales.
3. **Not applicable** — the state levies no general sales tax (DE, MT, NH, OR).
   There is no threshold to cross and nothing to register for.
4. **Unknown** — the engine cannot evaluate. See below; this is the important one.
5. **Triggered (economic)** — cumulative activity crosses the threshold
   (`combinator`-aware: sales-only, sales-or-transactions, sales-and-transactions).
6. **Approaching** — activity is within the configured band (default 80%) of the
   threshold. A watch signal, not an obligation.
7. **Below** — under the band.

## "I don't know" is not "you're fine"

**Unknown** exists because the two are different answers and must not share a
case. A state resolves to Unknown when:

- **the threshold cannot be resolved** — the dataset is fetched over the network,
  so a firewalled or misconfigured install resolves null for every state;
- **the ledger cannot answer** — `SalesLedger::activityFor()` returned null,
  meaning unbound, unreachable, not yet populated, or — for a host serving several
  sellers — asked without a subject to answer for. To assert that nothing was sold,
  a ledger returns `new SellerActivity(0, 0)`, which is what a real one does (a
  `SUM` over no rows is 0);
- **the totals were counted against a different question** — the host declared a
  `basis` or `period` on its activity that disagrees with what the state measures.
  Florida measures *taxable* sales over the *previous calendar year*; comparing
  gross calendar-YTD totals to its $100,000 is not an approximation of the answer,
  it is a different number.

Reporting `Below` in any of those cases reads as compliance. A seller could cross
every threshold in the country and see a clean board.

## Acting on the result

**Triggered** and **Unknown** both return `needsAction() === true`. Unknown does
not need a registration — it needs an operator to find out why the engine could
not answer. Treating it as inert is what let the gap stay invisible.

Each `NexusEvaluation` carries the `threshold`, `activity`, a `progress` ratio
(0–1+ toward crossing), `physicalPresence`, a human-readable `reason`, and
`caveats`.

### Caveats

`caveats` records what the verdict rests on that the engine **could not check** —
chiefly a host that fed totals without declaring which sales it counted or over
what window, or a state the dataset carries no basis for. A verdict with caveats
is still the best answer available, but it is not one the engine verified end to
end:

```php
$e = $engine->evaluate($state);

if ($e->isQualified()) {
    // "Register" — but which sales that rests on was never verified.
    foreach ($e->caveats as $caveat) { … }
}
```

## Reporting

A `NexusReport` buckets a set of evaluations into `triggered()`, `approaching()`,
`registered()`, `unknown()` and `notApplicable()`.

**Prefer `needingAction()`** for a dashboard's primary list. It rolls up triggered
*and* unknown in one call, so a consumer cannot ship a reassuringly empty board by
rendering three buckets and forgetting the fourth.

```php
$report = $engine->report($states);

$report->needingAction();   // crossed obligations AND states it could not evaluate
$report->notApplicable();   // DE, MT, NH, OR — nothing to comply with
```
