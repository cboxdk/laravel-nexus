---
title: Thresholds & measurement
weight: 23
description: The threshold figures plus the measurement period and sales basis that decide which sales, over which window, count.
---

# Thresholds & measurement

An `EconomicNexusThreshold` carries more than a dollar figure:

- `salesDollars`, `transactions`, `combinator` — the crossing test (`isMet`).
- `measuringPeriod` — the window a state measures over (previous / current /
  previous-or-current calendar year, or rolling twelve months). It tells **you**
  how to accumulate the `SalesLedger`.
- `salesBasis` — which sales count (gross / retail / taxable).
- `includesMarketplaceSales` — whether marketplace-facilitated sales count toward
  the seller's own threshold.

The engine's crossing test uses the figures; the measurement dimensions are
guidance for the host's accumulation, so the ledger you feed matches what each
state actually measures. All of this comes from the cited `us-tax-data` dataset
(`by-section/nexus.json`), a dated-window list so a pending threshold change is
carried until it takes effect.
