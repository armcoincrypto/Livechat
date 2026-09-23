# RATE_SYSTEM_FREEZE policy

```
RATE_SYSTEM_FREEZE=ACTIVE
```

Config marker: `config/rate_system_freeze.php`  
Golden fixture: `tests/fixtures/rates/golden_directions_freeze.json`  
Pre-deploy gate: `scripts/ops/rate_system_predeploy_gate.sh`

## Why

Closed incidents (XML flap, Zelle margin, Top-N overwrite, stored-course race, BC freshness, RUB orderability, admin pricing safety) must not regress via casual edits.

## Any future rate / XML change requires

1. Exact current **live** base SHA
2. Explicit owner + financial impact statement
3. Simulation (no live mutate)
4. Golden-direction regression
5. Global economics diff (`PUBLIC_QUOTE` / `ORDER_ECONOMICS` / Top-N / stored-course / mapping)
6. XML regression (online never empty; last-good)
7. Immutable candidate build
8. **RELEASE_COORDINATOR** approval + active release-owner lease
9. Post-deploy soak
10. Documented rollback point (`previous_release`)

## Role split

| Role | Allowed |
|---|---|
| FEATURE_AGENT | Branch, edit, test, commit. **STOP** before deploy/promote/rollback/canonical move/PM2 restart |
| RELEASE_COORDINATOR | Freeze → build → certify → promote with lease |
| EMERGENCY_ROLLBACK_OPERATOR | Rollback only with expected-current CAS |

## Hard fail gates (non-zero ⇒ block deploy)

- `PUBLIC_QUOTE_DEFECT_TOTAL`
- `ORDER_ECONOMICS_DEFECT_TOTAL`
- `TOPN_DEFECT_TOTAL`
- `STORED_COURSE_DEFECT_TOTAL`
- `POSITION_SELECTOR_DEFECT_TOTAL`
- `MAPPING_DEFECT_TOTAL`
- `UNKNOWN`
- SITE_ONLINE + empty XML
- Zelle→USDT six rows not matching owner/margin pins

## Forbidden without policy

- Pricing model redesign
- Casual commercial rate edits
- Weakening Vite/admin fail-closed gates
- Deploy from dirty checkout
- FEATURE_AGENT production cutover
