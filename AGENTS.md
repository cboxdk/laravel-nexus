# You are in `laravel-nexus`

Measures whether a seller has crossed a US state's **economic-nexus threshold** — the
sales volume or transaction count that creates an obligation to register and collect.

**It answers one question and stops there.** Whether nexus exists. Not what the rate
is once it does; that is `cboxdk/laravel-tax`.

## The boundary that keeps getting tested

This is **not a ledger package**. It measures against a window of sales the host
supplies; it does not own the sales. An earlier version drifted toward general
book-keeping and was pulled back deliberately — resist re-adding it.

Nor does it decide the rate. `NexusThreshold::isMet()` was removed from `laravel-tax`
for the same reason in reverse: the threshold belongs here, the rate belongs there.

## What is easy to get wrong here

**Every seam must carry the seller.** A threshold is per seller per state, and a
multi-tenant host that loses the seller identity silently measures one tenant's sales
against another's threshold. That was a real P0 once.

**A threshold has dimensions beyond the number.** Which sales count (gross vs retail
vs taxable), the measuring window (previous calendar year vs previous-or-current),
and whether marketplace-facilitated sales are included. A rule missing those is
carried as null — an honest "undetermined" — never a guessed default.

**The dataset is verified before it is believed.** The thresholds come from
`cboxdk/us-tax-dataset`, hash-checked against its manifest like every other consumer.

**Live branch: `main`.**

## The gate

`vendor/bin/pint --test` · `vendor/bin/phpstan analyse --memory-limit=1G` (level max) ·
`vendor/bin/pest`

The platform overview lives at `laravel-tax/PLATFORM.md`.
