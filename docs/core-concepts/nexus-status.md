---
title: The decision model
weight: 22
description: How each state resolves to Below, Approaching, Triggered or Registered — in precedence order.
---

# The decision model

For a state, `DefaultNexusEngine::evaluate()` resolves a `NexusStatus` in this
precedence:

1. **Registered** — the seller already holds a registration there (obligation
   handled).
2. **Triggered (physical)** — asserted physical presence establishes nexus on its
   own, whatever the sales.
3. **Triggered (economic)** — cumulative activity crosses the threshold
   (`combinator`-aware: sales-only, sales-or-transactions, sales-and-transactions).
4. **Approaching** — activity is within the configured band (default 80%) of the
   threshold. A watch signal, not an obligation.
5. **Below** — under the band, or no threshold/activity is known.

Only **Triggered** returns `needsAction() === true`. Each `NexusEvaluation` also
carries the `threshold`, `activity`, a `progress` ratio (0–1+ toward crossing),
`physicalPresence`, and a human-readable `reason`.

A `NexusReport` buckets a set of evaluations into `triggered()`, `approaching()`
and `registered()` for a platform dashboard.
