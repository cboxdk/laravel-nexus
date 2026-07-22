---
title: Installation
weight: 11
description: Install, and what the service provider binds by default.
---

# Installation

```bash
composer require cboxdk/laravel-nexus
```

The service provider auto-registers and binds:

- `NexusThresholdSource` → the dataset-backed source (reads `us-tax-data`'s
  `by-section/nexus.json` from the public mirror; see [configuration](../configuration/reference.md)).
- `SalesLedger`, `PhysicalNexus`, `NexusRegistrations` → **empty** defaults
  (deny-by-default). These are *your* data — rebind them (see
  [data seams](../extension-points/data-seams.md)).
- `NexusEngine` → the shipped `DefaultNexusEngine`.

Publish the config if you want to change the dataset location or the approaching
band:

```bash
php artisan vendor:publish --tag=nexus-config
```
