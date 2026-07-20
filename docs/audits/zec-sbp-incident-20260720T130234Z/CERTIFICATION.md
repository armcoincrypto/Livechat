# ZEC→SBP RATE INCIDENT + ACTIVE CATALOG ECONOMIC VALIDATION

Verification refresh: `2026-07-20T13:20Z` (live re-check after initial remediation).

## 1. Final verdict

```text
EXSWAPING_ZEC_SBP_QUARANTINED_PENDING_TRUSTED_BASELINE
```

ZEC→SBPRUB is fail-closed (orders + XML). Independent live ZECUSDT parser feed is still missing/stale. Full catalog has residual economic REVIEW/QUARANTINE flags on other coin→RUB rails (mostly OTC premium vs CBR, not ZEC-class circular pricing). No BestChange coin→RUB package rows are certified READY until those family policies are operator-approved.

## 2. Incident answer

| Field | Value |
|---|---|
| Root cause | Direction priced from BestChange peer/competitor data with **no live independent ZECUSDT baseline**. Prior health treated BestChange peer rate as baseline (`no_baseline_pass_through`), so ~9.7% uplift vs market was not blocked. |
| Direction ID | **541** ZEC→SBPRUB (BestChange `zcash-to-sbp`). Related ZEC→RUB also quarantined: **540** ZEC→SBERRUB, **542** ZEC→ACRUB. |
| Identity | Single SBP currency `SBPRUB` (id=12). No duplicate bank-specific SBP aliases beyond SBPRUB. |
| Period | Anomalous export observed until quarantine apply `2026-07-20 ~13:05Z` (`updated_at` 16:03:51 +03). XML rates seen in window ≈ 43286–45722 RUB/ZEC. |
| Customer orders | Direction 541: task **3093** only (2026-04-15, status=3, course ≈ 27846.52 — historical, not incident window). Direction 540: tasks 2401, 3806 (Feb/Jun 2026). **No orders in the BestChange-reported anomalous window.** |
| Financial loss | **Not evidenced** from tasks for 541 during the incident window. Potential treasury overpay risk prevented by quarantine. |

## 3. Rate calculation (before / independent reconstruction)

Orientation confirmed: XML/API `1 ZEC = X RUB` = customer sends 1 ZEC, receives X RUB. Not inverted.

```text
BEFORE (exported / reported):
  BestChange observed Exswaping: 1 ZEC = 45,722.79 RUB
  Freeze-sample course_value id541:     ≈ 43,286.90–43,287.00
  parser_source_name: BestChange
  profit / add_course / bc_your_add_course: 0
  Independent ZECUSDT parsers: status=0, summa≈54 (stale since 2024-11)

INDEPENDENT RECONSTRUCTION (2026-07-20 probes):
  Binance ZECUSDT bid ≈ 536.45 USDT
  CBR / russiancentralbank USD→RUB ≈ 78.26–78.40
  baseline ≈ 536.45 × 78.3987 ≈ 42,057 RUB / ZEC
  BestChange market reference (signal only): 41,670.51
  uplift vs BC ref ≈ +9.7% at 45,722.79
  uplift vs independent ≈ +8.7% at 45,722.79 / ≈ +2.9% at 43,287

AFTER:
  status=0, allow_export=2 (quarantined)
  absent from public /currencies.xml
  RateExportQuarantine without baseline → EXPORT_BLOCKED_NO_BASELINE
  crypto→RUB export gate refuses ZEC when cryptoRub('ZEC') is null
```

Exact formula with values (reconstruction):

```text
raw ZEC/USDT (Binance bid) 536.45
× USD/RUB (CBR/parser)     78.3987
× direction coefficient    1
± configured profit        0%
= justified band ≈ 42,057 RUB/ZEC
≠ exported 45,722.79 (BestChange-circular, unexplained)
```

## 4. Safety action

| Direction | Action |
|---|---|
| 541 ZEC→SBPRUB | **Quarantined** (status=0, allow_export=2). Kept blocked. Not re-enabled. |
| 540 ZEC→SBERRUB | Quarantined (same family, same defect class). |
| 542 ZEC→ACRUB | Quarantined (same family). |
| 543 ZEC→TCSBRUB | Already inactive (status=0, course=0). Untouched. |
| Other ZEC↔stable | Left active (not RUB/SBP incident). |

Rollback SQL: `zec_rub_quarantine_rollback.sql` (restores status=1, allow_export=0 only — does **not** re-enable export until trusted baseline exists).

## 5. Code / configuration changes

**Branch:** `fix/zec-sbp-rate-incident-20260720`  
**Tip SHA:** `6fdebcda006205430d8fd1884006abaab46fae1a`  
**Prior canonical:** `bd2bd81`  
**Deploy verify:** `rates:deploy-verify --commit=6fdebcd` → **drift=0 missing=0**

Commits (on tip, after bd2bd81):

1. `17e4330` fix(rates): fail-closed crypto→RUB export without independent baseline  
2. `dd1e3c2` feat(rates): prefer independent baselines in catalog economic audit  
3. `6fdebcd` test(rates): document ZEC→SBP incident quarantine and Kate handoff  

Key files:

- `app/Services/Rates/RateExportQuarantine.php` — default fail-closed on missing baseline  
- `app/Services/Rates/IndependentMarketBaseline.php` — `cryptoRub()`  
- `packages/Courses/Export/Concerns/ExportFormatHelpers.php` — `passesIndependentCryptoRubExportGate`  
- `tests/Unit/Rates/ZecSbpRateIncidentTest.php`  
- DB: `direction_exchange` ids 540–542  

## 6. Guard gap

| Before | After |
|---|---|
| Missing independent baseline → `no_baseline_pass_through` / EXPORT_ALLOWED when course > 0 | Missing baseline → `EXPORT_BLOCKED_NO_BASELINE` unless explicit `allow_no_baseline` |
| Health could be `critical=false` while ZEC→RUB used circular BC peers | `cryptoRub('ZEC')` gap surfaced in `baseline_gaps`; ZEC→RUB export gate fails closed |
| Aggregate catalog counts only | Direction-level economic audit + coin→RUB independent matrix |

Health still reports `critical=false` with intentional gaps listed (`ZECUSDT missing_or_stale`, etc.) — gaps are visible, not silently exportable for gated assets.

## 7. Full active-catalog audit

Economic audit (`economic_audit.json`):

| Class | Count |
|---|---|
| reviewed | 830 |
| PASS | 2 |
| PASS_EXPLAINED_SPREAD | 0 |
| REVIEW | 36 |
| QUARANTINE_REQUIRED | 26 |
| NO_BASELINE | 766 |

Coin→RUB independent matrix (41 directions, BestChange-relevant):

| Class | Count |
|---|---|
| QUARANTINE_REQUIRED | 26 |
| REVIEW | 9 |
| NO_BASELINE | 6 |

**Note:** Many `NO_BASELINE` rows are non-RUB or assets outside the independent coverage set (expected). Do **not** certify full catalog PASS from aggregates alone. Coin→RUB rows with CBR-relative premiums may be legitimate OTC spreads — operator family policy still required (see §14).

Live catalog: **active=830, quarantined=114, deprecated=91**.

## 8. Related direction matrix

| Pair | Status | Notes |
|---|---|---|
| ZEC→SBPRUB (541) | quarantined | Incident; absent from XML |
| ZEC→SBERRUB (540) | quarantined | Same BC-circular class |
| ZEC→ACRUB (542) | quarantined | Same |
| ZEC→TCSBRUB (543) | inactive | course=0 |
| Other ZEC→stable | active | Not RUB rails |
| BTC/ETH/BNB→SBPRUB | active, export gate ALLOWED | ~5–6.5% vs independent (below 7% critical block) |
| Several USDT*/USDC→SBPRUB | active DB, export gate BLOCKED | unexplained ≥ critical; absent from XML when gate applied |
| DASH/BCH/ETC/XMR→SBPRUB | active | Outside crypto asset gate list — residual coverage gap |

Live XML →SBPRUB (no ZEC): BCH, BNBBEP20, BTC, CASHUSD, DASH, ETC, ETH, USDTTON, XMR, ZELLEUSD.

## 9. Production health (live)

```text
runtime_drift: 0 (vs 6fdebcd)
invalid_active: 0
mapping_drift: 0
quarantined_direction_count: 114
active_no_baseline_count: 0
unexplained_critical_count: 0
critical: false
baseline_gaps: TONUSDT, ZECUSDT, LTCUSDT missing_or_stale
XML delivery: 20/20 HTTP 200, non-zero, ZEC→SBPRUB absent
xml-delivery-probe: ok=true, valid_xml=true
```

## 10. Tests

```bash
cd /var/www/app_exswapin_usr/data/www/app.exswaping.com
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit tests/Unit/Rates --do-not-cache-result
# OK (54 tests, 168 assertions)

sudo nginx -t
# syntax ok / test successful (existing MIME/map warnings only)

sudo -u app_exswapin_usr php8.4 artisan rates:deploy-verify --commit=6fdebcd
# drift=0 missing=0
```

## 11. BestChange handoff

```text
/opt/exswaping-zec-sbp-20260720/docs/audits/zec-sbp-incident-20260720T130234Z/BESTCHANGE_COIN_RUB_FINAL_V3.json
/opt/exswaping-zec-sbp-20260720/docs/audits/zec-sbp-incident-20260720T130234Z/BESTCHANGE_COIN_RUB_FINAL_V3.csv
/opt/exswaping-zec-sbp-20260720/docs/audits/zec-sbp-incident-20260720T130234Z/BESTCHANGE_MANUAL_RESPONSE_TO_KATE.txt
```

`eligible_for_submission`: **[]** (all 41 coin→RUB excluded under REVIEW / QUARANTINE_REQUIRED / NO_BASELINE / XML absence rules). Manual Kate message prepared; **not sent automatically**.

## 12. Rollback

**DB (quarantine undo — does not certify economic safety):**

```sql
-- file: zec_rub_quarantine_rollback.sql
UPDATE direction_exchange SET status=1, allow_export=0 WHERE id=540;
UPDATE direction_exchange SET status=1, allow_export=0 WHERE id=541;
UPDATE direction_exchange SET status=1, allow_export=0 WHERE id=542;
```

**Code:** `git checkout bd2bd81 --` listed rate files, or redeploy prior release tip before `17e4330`.  
**Do not** force-push. **Do not** re-enable allow_export for 541 without fresh ZECUSDT + reconstructed rate within unexplained threshold.

## 13. Safety

```text
no funds moved
no real test orders created
no secrets exposed
no guessed BestChange mappings (ZEC/SBPRUB VERIFIED)
no force-push
no automatic BestChange contact
historical tasks/orders preserved
XML not manually edited
global ZEC price not altered to hide defect
```

## 14. Remaining decisions

1. **Enable live Binance ZECUSDT** parser row `[binance_zec-usdt]` (id 344, currently status=0 / stale summa=54.2) and verify continuous refresh before any re-enable of 540–542.  
2. **Operator policy for RUB OTC premium vs CBR** on USDT/BTC→RUB rails (family thresholds) so legitimate payment-method spreads are EXPLAINED_SPREAD, not false QUARANTINE.  
3. **Extend crypto→RUB export gate asset list** (DASH/BCH/XMR/ETC/…) or require independent baseline for all exported crypto→RUB.  
4. Re-run coin→RUB package to populate `eligible_for_submission` only after (1)+(2).  
5. Do **not** re-enable ZEC→SBPRUB until rate is reconstructed from approved providers within unexplained-deviation policy and API/XML/order surfaces match.
