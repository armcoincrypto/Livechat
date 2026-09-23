# Zelle pricing model

## Scope

Outgoing Zelle USD → USDT* rails owned by `ZELLE_USDTTRC20_BENCHMARK`.

Canonical six rows (freeze pin):

| id | pair |
|---|---|
| 1525 | ZELLEUSD→USDTTRC20 |
| 1537 | ZELLEUSD→USDTERC20 |
| 1552 | ZELLEUSD→USDTSOL |
| 1556 | ZELLEUSD→USDTBEP20 |
| 1586 | ZELLEUSD→USDTTON |
| 3222 | ZELLEUSD→USDTPOLYGON |

## Mapping

- Admin «Прибыль» **+6** ⇔ storage `floating_fee=-6` ⇔ floating multiplier **0.94** at BASE=1.
- Floating mode uses `floating_fee` only.
- Fixed mode applies floating then `fix_fee` (canonical pin `fix_fee=-3` on the six rows).
- `profit` must stay **0** on these Zelle rows (profit column is not the Zelle knob).

## Reverse / non-outgoing Zelle

BestChange parser wins even on ZELLEUSD legs when `parser_source_name=BestChange`. Reverse Zelle / BC-owned rails stay on BC or manual ownership — do not force benchmark authority.

## Guards

- `CommercialAdjustmentResolver` / `CommercialAdjustmentWriteGate`
- `ZelleOutgoingRateWriteGuard`
- `ZelleUsdUsdtBenchmarkAuthority`

## Freeze rule

Any change to Zelle margins or ownership requires RATE_SYSTEM_FREEZE policy (simulation + golden regression + RELEASE_COORDINATOR).
