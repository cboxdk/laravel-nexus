# Cbox Nexus

The US economic-nexus engine for Laravel — accumulate a seller's sales per state,
detect when a post-*Wayfair* threshold is crossed, and report where registration is
required.

Cbox Nexus is UI-free engine primitives, not a finished application. It owns the
**decision** logic (below / approaching / triggered / registered per state); the
**data** — thresholds, the seller's cumulative activity, physical presence, and
existing registrations — is sourced behind contracts. Thresholds ship from the
cited `us-tax-data` dataset (with each state's measurement period and sales basis);
you bind your own billing data for the rest.

It never accumulates sales itself and never infers nexus from a single invoice —
economic nexus turns on the seller's *cumulative* totals in a state over a measuring
period, which the host supplies.

## Install

```bash
composer require cboxdk/laravel-nexus
```

## Quick use

```php
use Cbox\Nexus\Contracts\NexusEngine;
use Cbox\Geo\ValueObjects\SubdivisionCode;

$report = app(NexusEngine::class)->report([
    new SubdivisionCode('US-CA'),
    new SubdivisionCode('US-TX'),
]);

foreach ($report->triggered() as $evaluation) {
    // $evaluation->state, $evaluation->reason, $evaluation->threshold->describe()
}
```

Bind a `SalesLedger` (from your invoices), and optionally `PhysicalNexus` and
`NexusRegistrations`, so the engine has the seller's side of the equation.

## Documentation

See [`/docs`](docs/index.md): the [quickstart](docs/quickstart.md), the
[decision model](docs/core-concepts/nexus-status.md), and the
[data seams](docs/extension-points/data-seams.md) you bind.

## License

MIT.
