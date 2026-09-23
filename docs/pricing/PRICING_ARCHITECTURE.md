# Pricing architecture (canonical)

**Freeze:** `RATE_SYSTEM_FREEZE=ACTIVE` (see `docs/release/RATE_SYSTEM_FREEZE_POLICY.md`).

## Owners (source of truth)

| Owner | Parser / marker | BASE | Commercial knob («Прибыль») | Storage field |
|---|---|---|---|---|
| BESTCHANGE | `BestChange` | BestChange book at configured **position** | Margin % after BASE | `direction_exchange.profit` |
| ZELLE | `ZELLE_USDTTRC20_BENCHMARK` | USDTTRC20 benchmark (BASE≈1) | Margin % | `direction_exchange.floating_fee` (= −display) |
| DERIVED | `DERIVED_MARKET_BASELINE` | Internal market baseline | Margin % | `direction_exchange.profit` |
| MANUAL / OTHER | manual / other parsers | Stored / manual path | Context-specific | Usually `profit` |

Exactly **one** floating commercial knob for the floating quote — never `profit` + `floating_fee` together.

## BestChange formula (conceptual)

1. Position selects BASE from the live book.
2. Profit (margin %) applies **after** BASE.
3. Top-N / competition controllers must **never erase** configured profit.
4. Stored `course_value` must converge to the calculator; stale `manual_rate_value` must not overwrite BC authority.

## Admin contracts

- Truth / preview / converge: `AdminPricingOperatorSafety` + `/iexadmin` pricing health routes (read-only).
- Mass profit/position editor must not silently change OWNER / RATE_MODE / course_value.
- Owner badge in UI must reflect resolver family: BESTCHANGE | ZELLE | DERIVED | MANUAL/OTHER.

## Golden regression set

`tests/fixtures/rates/golden_directions_freeze.json` — Zelle→USDT six rows + representative BC pairs.

## What must never change casually

- Zelle→USDT `floating_fee=-6` on ids 1525,1537,1552,1556,1586,3222
- XML online last-good / empty policy
- Top-N profit floor
- Financial audit logger schema
- Vite/admin fail-closed gates (orthogonal but never weaken)
