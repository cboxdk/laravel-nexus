---
title: Data seams
weight: 41
description: Bind SalesLedger, PhysicalNexus, NexusRegistrations and NexusThresholdSource to your own data.
---

# Data seams

Rebind any contract in a service provider. The three host seams are empty by
default (deny-by-default).

## SalesLedger (required for economic nexus)

Your cumulative sales/transactions into each state, over the state's measuring
period. This is the seller's side of the equation — the package cannot know it.

```php
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\SellerActivity;

app()->singleton(SalesLedger::class, fn () => new class implements SalesLedger {
    public function activityFor(ActivityQuery $query): ?SellerActivity { /* from invoices */ }
});
```

The query carries the state's own terms — `basis`, `period`, and `windows()` giving
the concrete dates — so the sum can be run without looking the state's rules up
separately.

### Serving more than one seller

`$query->subject` names who the question is about. It is null when the host serves a
single seller, which is the common case and needs nothing.

If you serve several and the subject is null, **return null**; the engine reports
that as `Unknown`. Do not fall back on the current request or a container-scoped
singleton. A queued job or a long-lived worker carries whoever was served last, so
that fallback returns one seller's totals for another seller's state — silently,
and across a tenant boundary. `PhysicalNexus` and `NexusRegistrations` take the same
subject under the same rule; of the three a wrongly-claimed registration is the
worst, because it turns an outstanding obligation into a handled one on a dashboard.

## PhysicalNexus

States where the seller has an office, employees or inventory (e.g. FBA) — a nexus
trigger on its own. Host-asserted.

## NexusRegistrations

States the seller already holds a registration in, so a handled obligation reports
as `Registered` rather than an outstanding action.

## NexusThresholdSource

Defaults to the `us-tax-data` dataset. Rebind it to pin a different dataset copy or
supply thresholds from elsewhere; a state it doesn't carry returns `null`
(deny-by-default).
