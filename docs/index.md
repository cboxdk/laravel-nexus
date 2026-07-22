---
title: Cbox Nexus
weight: 1
description: The US economic-nexus engine for Laravel — decide where a seller has triggered a registration obligation, from cumulative sales, thresholds, physical presence and registrations.
---

# Cbox Nexus

Cbox Nexus answers one question, per US state: **has this seller triggered an
economic-nexus registration obligation?** It is a pure engine — it owns the
decision, and sources the data behind contracts.

## Mental model

```
thresholds (us-tax-data) ─┐
seller activity (host)   ─┤
physical presence (host) ─┼─▶  NexusEngine  ─▶  NexusEvaluation per state
registrations (host)     ─┘                      └▶ NexusReport (roll-up)
```

Each state resolves to a [`NexusStatus`](core-concepts/nexus-status.md):
**Registered** → **Triggered** (physical or economic) → **Approaching** → **Below**,
in that precedence.

## Sections

- [Getting started](getting-started/_index.md) — install, and test with the fakes.
- [Core concepts](core-concepts/_index.md) — the decision model and thresholds.
- [Cookbook](cookbook/_index.md) — evaluate a state, build a platform report.
- [Extension points](extension-points/_index.md) — the data seams you bind.
- [Configuration](configuration/_index.md) — the config keys.
