---
title: Requirements
weight: 3
description: The PHP, Laravel and package versions the resolver enforces.
---

# Requirements

From `composer.json` — only what the resolver enforces:

| Requirement | Constraint |
| --- | --- |
| PHP | `^8.4` (developed and CI-tested on 8.4 **and** 8.5) |
| Laravel (illuminate/*) | `^12.0 \|\| ^13.0` — current and previous major |
| `cboxdk/laravel-geo` | `^0.4 || ^0.5` (the `SubdivisionCode` value object) |

Dev tooling: Pest `^3.5 || ^4.0`, Orchestra Testbench `^10.0 || ^11.0`,
larastan `^3.0`, Pint `^1.18`. CI runs the matrix over PHP 8.4/8.5 × Laravel 12/13.
