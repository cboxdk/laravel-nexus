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
use Cbox\Nexus\ValueObjects\SellerActivity;
use Cbox\Geo\ValueObjects\SubdivisionCode;

app()->singleton(SalesLedger::class, fn () => new class implements SalesLedger {
    public function activityFor(SubdivisionCode $state): ?SellerActivity { /* from invoices */ }
});
```

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
