# Fast Market Plane (30–60s customer rate freshness)

Source-only design for Exswaping. **Not deployed.** Flags default `false`.

## Problem

Customer quotes/orders read persisted `bestchange_directions.rate_value` via
`CalculatorFacade`. That value is written by `compiler:bestchange` (~15–25m).
The ~60s BestChange XML parser does **not** feed the calculator.

## Rule

`ONE_CANONICAL_PRICING_ENGINE`

- Market → `rate_value`: `BestChangeMarketPipeline` + `RateCalculator` (+ sanity)
  via `BestChangeBookRateComputer` (same primitives as `RatesUpdateService`)
- Commercial quote: `CalculatorFacade` / strategies (unchanged)
- Full compiler remains async reconciliation — **not** customer freshness owner
  once fast quote flags are enabled

## Components

| Piece | Role |
|---|---|
| `BestChangeMarketSnapshotProducer` | `fetchRatesBatch` once → Redis snapshot |
| `MarketSnapshotStore` | atomic cache put/get |
| `BestChangeBookRateComputer` | snapshot book → rate_value (no DB write) |
| `FastMarketQuoteService` | hydrate + CalculatorFacade; shadow compare |
| `FastMarketSourceRouter` | per-family routing (BC only uses snapshot) |
| `rates:fast-market-snapshot-refresh` | scheduled producer when plane enabled |
| `rates:fast-market-equivalence` | offline/online mismatch classifier |

## Flags (all default false)

```
FAST_MARKET_PLANE_ENABLED=false
FAST_PUBLIC_QUOTE_ENABLED=false
FAST_ORDER_QUOTE_ENABLED=false
FAST_MARKET_SHADOW_ENABLED=false
```

With all false: `PRODUCTION_BEHAVIOR_DIFF=NONE`.

## Freshness policy

| State | Default age |
|---|---|
| FRESH | ≤60s |
| STALE_BUT_USABLE | ≤120s |
| EXPIRED | >120s (fallback to persisted if age ≤1200s) |

## Order model

**A — recalculate from latest usable snapshot** at order create when
`FAST_ORDER_QUOTE_ENABLED`. No signed quote token in v1. `QUOTE_TTL=60s`
is the snapshot usability window, not a locked customer promise.

## Cutover order (future — not this task)

1. Enable plane + shadow; measure snapshot age / shadow diffs
2. Certify `ALGORITHM_MISMATCH_COUNT=0` on same-snapshot replay
3. Enable public quote canary
4. Enable order quote
5. Separate task: simplify scheduler catch-up only after quotes leave compiler freshness

## Do not

- Run full compiler every 30–60s
- Duplicate margin/position/rounding math
- Remove overdue catch-up / locks yet
- Deploy from this branch without owner approval
