# Fast Market Plane (30–60s customer rate freshness)

Live flags are read from `config/fast_market.php` / env (`FAST_MARKET_*`).
As of 2026-11 production cutovers, plane + public/order quote are **enabled**;
treat this doc as architecture + ops reference, and verify live flags before changes.
Defaults in repo examples remain `false` for safe fresh installs.

## Problem

Customer quotes/orders read persisted `bestchange_directions.rate_value` via
`CalculatorFacade`. That value is written by `compiler:bestchange` (~15–25m).
The ~60s BestChange XML parser does **not** feed the calculator.

### Legacy “~30 seconds” (archaeology)

True historical ~30s loop was **`/var/www/rates` dual cron** (`* * * * *` +
`sleep 30` → `scripts/generate.php`) writing **XML only** (no `course_value`).
Disabled **2026-04-09**. Separately, pre-`1d676d9` Laravel `scheduleMap` mapped
setting value **4 → everyThirtySeconds**; live setting **30** now correctly means
**everyThirtyMinutes**. Active XML daemon: `/opt/bestchange_parser` at **60s**.

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

## 30-second producer + config-cache-safe flags (2026-10-27)

- Flags live in `config/fast_market.php`; runtime code uses `config('fast_market.*')` via `FastMarketFlags` (`DIRECT_RUNTIME_ENV_READS=0`).
- Preferred cadence: systemd `exswaping-fast-market-producer.service` → `rates:fast-market-producer-loop` (monotonic ~30s boundaries).
- Laravel `everyMinute` refresh is **opt-in fallback** only (`FAST_MARKET_SCHEDULER_FALLBACK=true`).
- Snapshot age source: `fetched_at` (BestChange batch has no reliable per-book source clock; `SOURCE_TIMESTAMP_RELIABLE=NO`).
- Setter: `scripts/ops/set_fast_market_runtime_flag.sh` updates shared `.env`, clears/rebuilds config cache, asserts effective values.
