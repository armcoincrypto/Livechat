# TRUSTED RATE BASELINE RECOVERY + ACTIVE CATALOG CERTIFICATION

Evidence: `docs/audits/baseline-recovery-20260720T133137Z/`  
Continued from: `EXSWAPING_ZEC_SBP_QUARANTINED_PENDING_TRUSTED_BASELINE`

## 1. Final verdict

```text
EXSWAPING_ZEC_BASELINE_RESTORED_RUB_POLICY_PENDING
```

Binance ZECUSDT (and LTCUSDT) independent feeds are live and refreshing. ZEC→RUB directions **540/541/542 remain quarantined**. RUB family premium policy exists as **DRAFT (`approved=false`)** — BestChange V4 eligible set is empty until operator approval. Catalog has partial economic improvement via USDT-bridge baselines; not a full active-catalog PASS.

## 2. Executive answer

| Metric | Before | After |
|---|---|---|
| active | 830 | 830 |
| quarantined | 114 | 114 |
| deprecated | 91 | 91 |
| economic PASS | 2 | **61** |
| PASS_EXPLAINED_SPREAD | 0 | **1** |
| REVIEW | 36 | 40 |
| QUARANTINE_REQUIRED | 26 | 28 |
| NO_BASELINE | 766 | **700** |

NO_BASELINE reduced mainly by crypto→crypto USDT bridging for covered assets. Remaining 700 grouped by family (see `ACTIVE_ASSET_BASELINE_GAPS.json`).

## 3. ZEC

| Field | Value |
|---|---|
| Outcome | **ENABLE_EXISTING_ZEC_FEED** |
| Provider | Binance (`group_parser_exchange` id=10, alias=binance) |
| Symbol | ZECUSDT — exchangeInfo `TRADING` |
| Parser row | id **344** `[binance_zec-usdt]` status 0→**1** |
| Also enabled | id **228** `[binance_ltc-usdt]` (baseline gap closure) |
| Freshness | Live updates confirmed across cycles (e.g. summa 535→530 range; age ≪ 900s) |
| Independent baseline | `ZECUSDT × USDRUB` ≈ **41,550–42,050 RUB/ZEC** (moves with market) |
| Direction 540 | quarantined (blocked) |
| Direction 541 | quarantined (blocked); `baseline_status=OK`, export/order false |
| Direction 542 | quarantined (blocked) |
| Restored to BestChange? | **No** — stop condition: RUB premium policy not approved |

Rollback SQL: `parser_enable_zec_ltc_rollback.sql`

## 4. Provider coverage

See `ACTIVE_ASSET_BASELINE_COVERAGE.json` / `ACTIVE_ASSET_BASELINE_GAPS.json`.

| Status | Assets |
|---|---|
| DIRECT_BASELINE | BTC, ETH, BNB, TRX, ZEC, LTC (+ USDT pairs) |
| FIAT_CONVERSION | USDRUB (CBR) for stables→RUB |
| NO_APPROVED_PROVIDER | **TON** (no live TONUSDT parser row) |
| Unsupported / uncovered dest | DASH, XMR, SOL, XRP, ADA, cash/Zelle, etc. |

Top NO_BASELINE families: `USDT_uncovered_dest` (252), `USDC_uncovered_dest` (52), unsupported DASH/XMR/… — not mass-quarantined.

## 5. RUB policy

File: `resources/rates/rub-family-premium-policy.json`  
Service: `App\Services\Rates\RubFamilyPremiumPolicy`

```text
approved: false
families: SBPRUB, SBERRUB, TCSBRUB, ACRUB, CARDRUB, CASHRUB, OTHER_RUB
proposed bands: typically 0–8% configured premium (draft)
```

Until `approved=true`, premiums **do not** explain deviations for certification. Operator must sign checklist in the JSON.

## 6. Unsafe directions (original 26)

All **26** remain `KEEP_BLOCKED_PENDING_RUB_POLICY`  
Artifact: `QUARANTINE_REQUIRED_26_OUTCOMES.json`  
No automatic re-enable.

## 7. No-baseline closure

Grouped by family (not 700 one-offs):

- Bridged baselines added for covered crypto↔crypto via USDT
- Crypto→RUB for covered assets when feeds fresh
- Uncovered destinations / unsupported coins → documented gaps; candidates for internal-only or deprecation later
- Did **not** mass-quarantine 700 directions

## 8. API / order / XML parity

- ZEC→SBPRUB absent from public XML (`xml_20_probe*: 20/20, zec_sbp=0`)
- Quarantined directions: order/export eligibility false on all surfaces
- Direction-status now reports `baseline_path/rate/status` and requires independent baseline for crypto→RUB explain path
- Restored directions: none (parity N/A for re-enable)

## 9. Reserve policy

Unchanged canonical source: `currency2_effective_reserve` / direction reserve by `type_reserve`. No fabricated reserves published. ZEC directions remain non-exported.

## 10. BestChange package V4

```text
docs/audits/baseline-recovery-20260720T133137Z/BESTCHANGE_COIN_RUB_FINAL_V4.json
docs/audits/baseline-recovery-20260720T133137Z/BESTCHANGE_COIN_RUB_FINAL_V4.csv
docs/audits/baseline-recovery-20260720T133137Z/BESTCHANGE_MANUAL_RESPONSE_TO_KATE_V2.txt
```

`eligible_for_submission`: **0** (policy pending + economic classes).

## 11. Tests

```bash
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit tests/Unit/Rates --do-not-cache-result
# OK (61 tests, 187 assertions) — includes TrustedBaselineRecoveryTest
sudo nginx -t  # syntax ok
```

## 12. Production

| Item | Value |
|---|---|
| Incident certified code base | `6fdebcd` (+ recovery changes on branch) |
| Docs tip prior | `0c19e7e` |
| ZEC parser | live status=1 |
| Health | `critical=false`, gap remaining: TONUSDT; ZEC/LTC gaps cleared |
| XML | stable, ZEC→SBPRUB absent |
| Deploy drift vs 6fdebcd | recovery files intentionally newer (verify against release tip after commit) |

## 13. Rollback

```text
Parser: parser_enable_zec_ltc_rollback.sql  (status=0 for ids 344,228)
Directions 540–542: already quarantined — no status change in this phase
Code: revert release branch commits / redeploy prior tip
Policy: delete or keep draft JSON with approved=false (safe default)
XML: no manual XML edits; regenerate via normal pipeline
```

## 14. Safety

```text
no funds moved
no real customer orders created
no secrets exposed
no guessed mappings
no force-push (attempted push only with existing server keys)
no automatic BestChange contact
historical data preserved
no uncertified direction restored
```

## 15. Remaining operator actions

1. Approve or revise `rub-family-premium-policy.json` (`approved=true` + sign-off).  
2. Decide TONUSDT source (add Binance/WhiteBit row or keep TON blocked for independent baseline).  
3. After policy approval: recertify coin→RUB, optionally restore 540/541/542 **individually** only at PASS / PASS_EXPLAINED_SPREAD.  
4. Push/merge `release/exswaping-zec-rate-incident-20260720` with writable credential if read-only deploy key blocks GitHub.  
5. Deprecate or mark internal-only low-demand unsupported assets (DASH/XMR/… families) when product agrees.
