---
title: Architecture
weight: 21
description: A pure decision engine over four sourced data seams — contracts-first, deny-by-default.
---

# Architecture

Cbox Nexus separates the **decision** (which it owns) from the **data** (which it
sources behind contracts):

| Seam (contract) | Supplies | Default binding |
| --- | --- | --- |
| `NexusThresholdSource` | each state's threshold + measurement rules | the `us-tax-data` dataset |
| `SalesLedger` | the seller's cumulative sales/transactions per state | empty (host binds it) |
| `PhysicalNexus` | states with asserted physical presence | empty |
| `NexusRegistrations` | states the seller already registered in | empty |

`DefaultNexusEngine` composes them and produces a
[`NexusEvaluation`](nexus-status.md) per state, or a `NexusReport` across many.

Principles:

- **Contracts-first.** Depend on the interfaces; rebind any of them.
- **Deny-by-default.** No threshold or no activity → no economic claim (`Below`).
  No physical/registration assertion → none is assumed.
- **Never infers from one supply.** The engine compares *host-supplied cumulative
  totals* to thresholds; it does not accumulate sales or watch invoices.
