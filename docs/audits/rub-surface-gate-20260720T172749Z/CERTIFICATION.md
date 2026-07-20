# RUB POLICY FULL-SURFACE ENFORCEMENT + RATE AUDIT RELEASE

Evidence: `docs/audits/rub-surface-gate-20260720T172749Z/`  
Continued from: `EXSWAPING_RATE_AUDIT_PASS_WITH_BLOCKED_DIRECTIONS`

## 1. Final verdict

```text
EXSWAPING_RUB_POLICY_FULL_SURFACE_ENFORCEMENT_PASS
```

API quote, order validation, XML export, and BestChange eligibility now share one canonical decision via `RateDirectionEligibility::evaluateDirection()` → `RubFamilyPremiumPolicy::evaluateCoinRub()`.

## 2. Main-goal result

| Surface | Gate |
|---|---|
| Website quote (`DirectionDetailResource`) | `quote_allowed` |
| Partner/API quote (`OperationDetailResource`) | `quote_allowed` |
| Order create (`ValidatesOrderRules`) | `order_allowed` (blocks before `Task::create`) |
| XML / BestChange (`ExportFormatHelpers`) | `export_allowed` / `BestChange_allowed` |
| `rates:direction-status` | same `evaluateDirection` |

REVIEW: quote may show; **order+export blocked**.  
QUARANTINE / NO_BASELINE / NO_POLICY: quote+order+export blocked.  
PASS / PASS_EXPLAINED: all allowed when reserve+mapping pass.

## 3. Direction 268

| Surface | Result |
|---|---|
| Quote | blocked (`DIRECTION_TEMPORARILY_UNAVAILABLE`) |
| Order preview/create | blocked (validation error; no Task row) |
| XML | absent |
| BestChange | not allowed |
| Historical impact | **0 orders** since policy approval window; last lifetime order 2026-07-01 — **no loss evidenced** |

## 4. Coin→RUB surface parity

From `COIN_RUB_SURFACE_ELIGIBILITY_PARITY.json` (active coin→RUB with known asset):

| Metric | Count |
|---|---|
| reviewed | 69 |
| quote allowed | 68 |
| order allowed | 61 |
| export / BestChange allowed | 61 |
| mismatch | **0** |
| orderable REVIEW | **0** |

## 5. Git / release

See `RELEASE_GIT.json`.

| Field | Value |
|---|---|
| branch | `release/exswaping-rate-audit-final-20260720` |
| branch / remote SHA | `a600d8d` |
| main before | `6fdebcd` |
| merge / main after / production | `9d73a68` |
| deploy drift | **0** |
| deploy missing | **0** |

## 6. Tests

```bash
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit tests/Unit/Rates --do-not-cache-result
# OK (76 tests, 226 assertions)
```

## 7. Production health

- critical: false
- runtime_drift: 0
- baseline_gaps: TONUSDT only
- economic-audit totals: PASS 85, PASS_EXPLAINED 37, REVIEW 9, QUARANTINE_REQUIRED 0, NO_BASELINE 700 (831 reviewed)
- XML delivery probe: ok (valid_xml, ~821–822 items); 268/540 absent; 541/542 present

## 8. ZEC

| ID | Status |
|---|---|
| 540 | KEEP_QUARANTINED (operator decision package ready) |
| 541 | certified — quote/order/export allowed |
| 542 | certified — quote/order/export allowed |

## 9. Rollback

- Code: revert release commits / restore prior files
- Direction 268: remains quarantined; restore only via `dir_268_quarantine_rollback.sql` from full-rate-audit (not recommended)
- 540: stay quarantined unless operator RESTORE

## 10. Safety

```text
no funds moved
no real test orders created
no secrets exposed
no force-push
no automatic BestChange contact
historical data preserved
no uncertified direction restored
```

## 11. Remaining work

- ZEC 540 operator decision (RESTORE vs KEEP)
- CARDAMD / PPUSD policy
- NO_BASELINE family closure
- TON baseline decision
