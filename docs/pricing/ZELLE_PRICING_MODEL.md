# Zelle pricing model

## Scope

Both Zelle legs owned by `ZELLE_USDTTRC20_BENCHMARK`:

| Leg | BASE |
|---|---|
| **ZELLEUSD → dest** | USDTTRC20→dest benchmark hierarchy (stable peg / peer / IMB) |
| **coin → ZELLEUSD** | Exact inverse of the outgoing peer BASE (`1 / outgoing`) |

Canonical USDT six rows (freeze pin):

| id | pair |
|---|---|
| 1525 | ZELLEUSD→USDTTRC20 |
| 1537 | ZELLEUSD→USDTERC20 |
| 1552 | ZELLEUSD→USDTSOL |
| 1556 | ZELLEUSD→USDTBEP20 |
| 1586 | ZELLEUSD→USDTTON |
| 3222 | ZELLEUSD→USDTPOLYGON |

## Mapping (admin control)

- Admin «Прибыль» **+6** ⇔ storage `floating_fee=-6` ⇔ floating multiplier **0.94** at BASE.
- Floating mode uses `floating_fee` only.
- Fixed mode applies floating then `fix_fee` (canonical pin `fix_fee=-3`).
- `profit` must stay **0** on Zelle BENCHMARK rows (profit column is not the Zelle knob).
- Preview: `php artisan rates:zelleusd-usdt-preview --all --json`
- Refresh / unify: `php artisan rates:zelle-crypto-unify --apply --enable-usdc-xml`

## Consistency rule

Crypto→Zelle and Zelle→Crypto share one authority. Do **not** leave inbound on BestChange/Manual/Derived while outbound is BENCHMARK — that creates asymmetric customer economics and admin confusion.

BestChange may still own a Zelle **outgoing** row only when `parser_source_name=BestChange` **and** active `bestchange_directions` (intentional override). Inbound BENCHMARK rows deactivate competing BC mappings.

## Guards

- `CommercialAdjustmentResolver` / `CommercialAdjustmentWriteGate`
- `ZelleOutgoingRateWriteGuard` (outgoing + inbound-BENCHMARK)
- `ZelleUsdUsdtBenchmarkAuthority`
- Calculator `SourceChecks::zelle` (both legs)

## Freeze rule

Any change to Zelle margins or ownership requires RATE_SYSTEM_FREEZE policy (simulation + golden regression + RELEASE_COORDINATOR).
