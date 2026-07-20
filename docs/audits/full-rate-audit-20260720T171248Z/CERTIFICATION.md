# EXSWAPING — FULL RATE ENGINE DEFECT AUDIT AND ECONOMIC CORRECTNESS CERTIFICATION

Evidence: `docs/audits/full-rate-audit-20260720T171248Z/`  
UTC freeze: **2026-07-20T17:12:48Z**  
Host: `exswaping.com`

## 1. Final verdict

```text
EXSWAPING_RATE_AUDIT_PASS_WITH_BLOCKED_DIRECTIONS
```

Active exported coin→RUB BestChange set is economically gated (PASS / PASS_EXPLAINED only) with **API/XML parity 58/58**. One confirmed order-path defect (USDTTRC20→SBPRUB **268**) was fail-closed. REVIEW coin→RUB directions remain export-blocked by policy. ZEC **540** stays quarantined pending individual restore. Large NO_BASELINE catalog and deploy git-drift remain open but do not leave critical unexplained premiums active in XML.

## 2. Executive answer

| Metric | Value |
|---|---|
| Active directions reviewed (economic-audit) | **831** |
| PASS | **79** |
| PASS_EXPLAINED_SPREAD | **34** |
| REVIEW | **18** (catalog) / **12** coin→RUB |
| QUARANTINE_REQUIRED (active) | **0** (268 removed from active via DB quarantine) |
| NO_BASELINE | **700** |
| NO_POLICY | **0** |
| NO_RESERVE (eligible set) | **0** |
| PARITY_FAILURE (eligible) | **0** |
| BestChange-eligible coin→RUB | **58** |
| Confirmed defects | **3** (1 fixed via quarantine, 1 diagnostic fixed, 1 partially mitigated) |
| Fixed this audit | **268 quarantine** + **direction-status family overlay** |
| Blocked directions | **268**, **540**, REVIEW exports, TON, OTHER_RUB, NO_BASELINE |

## 3. Confirmed broken rates

### DEF-001 — USDTTRC20→SBPRUB (268) — FIXED (quarantined)

| Field | Value |
|---|---|
| Old rate | 83.910112… |
| Baseline | CBR USDRUB 78.3159 |
| Expected @ SBP target 5% | ≈82.2317 |
| Raw / unexplained | ≈7.14% / ≈2.14% |
| Root cause | BestChange-priced rate above approved SBP target by >2%; **order/API did not enforce family quarantine** while XML gate did |
| Impact | No orders in audit window since 2026-07-01; loss **not evidenced** in window |
| Fix | `status=0`,`allow_export=2` + rollback SQL |
| Final | **KEEP_QUARANTINED** |

### DEF-002 — `rates:direction-status` false export eligibility — FIXED

REVIEW directions previously reported `eligible_for_export=true`. Overlay now applies `evaluateCoinRub`.

### DEF-003 — Order/API hot path lacks family gate — PARTIALLY_MITIGATED

Systemic remaining work: wire order creation to family `order_allowed` (or equivalent). Mitigated for 268 via DB quarantine.

See `CONFIRMED_DEFECTS.json`, `BROKEN_RATE_FINDINGS.json`.

## 4. Provider defects

| Item | Status |
|---|---|
| Enabled parser rows | 80 |
| Stale enabled (>900s) | 4 (BGN-RUB, USDT-USD×2, USD-KZT) — documented |
| Duplicate enabled pairs | 12 groups (Binance+WhiteBIT medians OK; AMD floatrates median OK) |
| TONUSDT | **UNSUPPORTED_OR_STALE** — KEEP_TON_DISABLED |
| ZECUSDT / LTCUSDT | Live USDT_BRIDGE |
| USDRUB | CBR fresh |

Artifact: `PROVIDER_COVERAGE.json`

## 5. Formula defects

| Finding | Result |
|---|---|
| Multiply/divide inversion | **Not found** on reviewed coin→RUB exceptions |
| Duplicate profit | **Not found** as root of REVIEW band |
| Bid/ask inversion | N/A (spot mid from parser summa) |
| BestChange as independent mid | **Forbidden**; baselines from `parser_exchange` |
| Root of REVIEW premiums | BestChange commercial OTC markup in family **warning** band |

Canonical path: `CANONICAL_RATE_PATH.md`  
Provider/baseline summary: `PROVIDER_BASELINE_COVERAGE.md` + `PROVIDER_COVERAGE.json`

## 6. Coin→RUB exceptions (prior 17 REVIEW / 7 QUARANTINE)

Live recheck (market moved):

| Outcome | Directions |
|---|---|
| **KEEP_EXPORT_BLOCKED_REVIEW** | 14,25,26,160,223,1029,1056,1058,1070,1075,1420 (+ oscillates) |
| **KEEP_QUARANTINED** | **268** (was only active QUARANTINE_REQUIRED) |
| Prior larger QUARANTINE set | Collapsed as premiums moved; not widened thresholds |

Numeric traces: `REVIEW_QUARANTINE_TRACES.json`, `REVIEW_DIRECTIONS.json`

## 7. ZEC

| ID | Pair | Status |
|---|---|---|
| **540** | ZEC→SBERRUB | **KEEP_QUARANTINED_PENDING_INDIVIDUAL_RESTORE** — economically PASS_EXPLAINED_SPREAD now, but still `status=0`; not auto-restored |
| **541** | ZEC→SBPRUB | Active certified; in XML 20/20 |
| **542** | ZEC→ACRUB | Active certified; in XML 20/20 |

540 reconstruction (example): ZECUSDT × USDRUB ≈ mid; DB course within target band (raw ~2.8%). Restore only via individual certification + fresh live rate refresh.

## 8. Reserve defects

Eligible coin→RUB set: effective reserves via `ReserveLinkResolver` / `currency2_effective_reserve` — **no zero-reserve exports** in eligible set. No fabricated reserves.

## 9. API / order / XML parity

| Check | Result |
|---|---|
| Eligible DB↔XML parity | **58/58 OK** (`ACTIVE_DIRECTION_RATE_PARITY.json`) |
| Public XML probe | **20/20** |
| 268 / 540 absent from XML | **Confirmed** |
| Order vs stored course | Live Calculator; family gate still not on order path (DEF-003) |

## 10. Historical impact

| Scope | Result |
|---|---|
| Window since 2026-07-19 for focus IDs | 1 order (dir 14) — insufficient to claim loss |
| 268 lifetime | 104 orders; last 2026-07-01 — **no loss evidenced in this window** |
| 540/541 | Historical only; no anomaly-window orders |

Artifact: `ORDER_IMPACT_REVIEW.json` — **no loss evidenced** / **insufficient evidence** for treasury loss claims.

## 11. Production health

| Item | Value |
|---|---|
| Worktree HEAD | `e11a6e0` (+ uncommitted audit/policy files) |
| Production git tip | `59a1895` |
| origin/main | `6fdebcd` |
| Deploy-verify vs prod tip | **drift=21** (live APP matches WT workdir; ahead of prod git tip) |
| Health critical | **false** |
| Baseline gaps | TONUSDT only |
| Quarantined directions | **113** |
| XML items | **819** authoritative |
| XML authority | `public/static/exports/currencies.xml` authoritative; changed twin cert copy; `public/currencies.xml` legacy match |

## 12. Tests

```bash
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit tests/Unit/Rates --do-not-cache-result
# OK (72 tests, 216 assertions)
php8.4 -l app/Console/Commands/RatesDirectionStatusCommand.php  # OK
```

## 13. Rollback

See `ROLLBACK.json`:

- `dir_268_quarantine_rollback.sql`
- Direction-status overlay: revert file from git / prior SHA

## 14. Safety

```text
no funds moved
no real test orders created
no secrets exposed
no guessed mappings
no force-push
no automatic BestChange contact
historical data preserved
no uncertified direction restored (540 kept quarantined)
```

## 15. Remaining work (real only)

1. **Commit + push** release branch so deploy-verify drift clears against a certified SHA.  
2. **Order/API family gate** (DEF-003 systemic) — smallest owner extension of existing policy, not a new pipeline.  
3. **Individual restore of ZEC 540** when operator requests (already economically PASS_EXPLAINED).  
4. **CARDAMD duplicate catalog** + **PPUSD premium policy** decisions.  
5. **NO_BASELINE coverage** / TONUSDT — keep fail-closed; no mass quarantine.

## Phase 1 gate note

Mutations were limited after documenting drift=21 as **live==worktree certified patches not in prod git tip**. Safety quarantine of 268 proceeded with exact rollback because leaving it orderable was a confirmed treasury-risk defect.
