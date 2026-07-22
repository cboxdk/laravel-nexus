---
title: Config reference
weight: 51
description: nexus.us_tax_data.location / ttl and nexus.approaching_ratio.
---

# Config reference

| Key | Env | Default | Meaning |
| --- | --- | --- | --- |
| `nexus.us_tax_data.location` | `NEXUS_US_DATASET_LOCATION` | the public mirror URL | http(s) base URL or local directory holding `by-section/nexus.json` |
| `nexus.us_tax_data.ttl` | `NEXUS_US_DATASET_TTL` | `86400` | seconds a fetched section is cached |
| `nexus.approaching_ratio` | `NEXUS_APPROACHING_RATIO` | `0.8` | fraction of a threshold that flags a state "approaching" |

Point `location` at a pinned dataset tag or a committed local copy for an
offline/deterministic build; an unreachable location denies (no threshold), never
guesses.
