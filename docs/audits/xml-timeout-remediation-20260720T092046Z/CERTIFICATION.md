# EXSWAPING — PUBLIC XML TIMEOUT REMEDIATION AND RATE-CATALOG DECISION CLOSURE

**Generated (UTC):** 2026-07-20T09:30:00Z  
**Evidence:** `docs/audits/xml-timeout-remediation-20260720T092046Z/`  
**Baseline remote SHA:** `d1785f37753f11c7d02a3383d975bef9469a7ab9`  
**Worktree branch:** `fix/xml-timeout-atomic-publish-20260720`

## 1. Final verdict

```text
EXSWAPING_PUBLIC_XML_TIMEOUT_AND_CATALOG_CLOSURE_PASS
```

## 2. XML timeout root cause

| Field | Value |
|---|---|
| Failure class | `ATOMIC_WRITE_FAILURE` |
| Supporting evidence | nginx `exswaping.com-frontend.error.log`: `pread() read only 0 of 32768 from .../public/static/exports/currencies.xml while sending response to client` while `scheme:files` rewrote via non-atomic `File::put()` |
| Why localhost/prior checks missed it | Local curls between generations often hit a complete file; race is intermittent under concurrent BestChange fetch + minute cron. IPv6 local timeouts are a **separate** non-public path (no AAAA). |

## 3. XML delivery fix

| Change | Detail |
|---|---|
| Writer | `AtomicPublicXmlPublisher` + `ExportCourses::put()` temp→fsync→rename; clear() no longer truncates live file; last-good retained |
| Export filter | VERIFIED-only mapping (`ExportFormatHelpers`) blocks TON/Payeer |
| nginx | Static `location = /currencies.xml` alias unchanged path; charset utf-8; `Cache-Control: no-cache`; narrow access_log |
| Monitor | `php8.4 artisan rates:xml-delivery-probe --ipv4` |
| LTC | `currencies.status` LTC `1→0` (export builder requires currency status=0) |
| Reserve tooling | `rates:direction-status` uses currency effective reserve |

Nginx backup: `/etc/nginx/fastpanel2-sites/exswaping_co_usr/exswaping.com.conf.bak-xml-timeout-20260720T092621Z`  
LTC rollback SQL: `ltc_currency_status_rollback.sql`

## 4. Reliability evidence

From `reliability_matrix.json` (IPv4 public URL):

| Metric | Result |
|---|---|
| Sequential requests | 20 |
| Success rate | 100% |
| Timeouts / zero-byte / invalid XML | 0 / 0 / 0 |
| p50 / p95 / p99 TTFB | ~0.085 / ~0.093 / ~0.093 s |
| Max total | ~0.094 s |
| During generation | 5/5 success, 0 zero-byte |
| HTTP/1.1 | 200 |
| HTTP/2 negotiate | 200 (observed HTTP/1.1 on this edge) |
| IPv6 public | No AAAA advertised — N/A for BestChange |
| Items / size | 832 / 161847 |

## 5. LTC → RUB

| Direction | Pair | Root cause | Fixed |
|---|---|---|---|
| 160 | LTC→SBPRUB | LTC `currencies.status=1` excluded by export builder | yes |
| 223 | LTC→SBERRUB | same | yes |
| 224 | LTC→ACRUB | same | yes |
| 225 | LTC→TCSBRUB | same | yes |
| 226 | LTC→RFBRUB | same | yes |
| 1420 | LTC→YAMRUB | same | yes |

- Fixed count: **6**
- Still excluded: **0**
- Final eligible coin→RUB total: **41** (was 35)

## 6. TON

- 1765 / 1778: active internally, **not** export-eligible, **absent** from public XML  
- All TON public exports: removed via fail-closed mapping  
- GRAM (209) remains VERIFIED and separate  
- Provider: keep TON disabled; no new provider

## 7. Payeer

- PRUSD / PREUR / PRRUB: ABSENT mappings; absent from public XML; internal-only  
- Do not map to BC 108 (CARDVND)

## 8. CoinMarketCap

`KEEP_CMC_DISABLED` — plan/credential failure; not required for active corridors covered by Binance/WhiteBit/Coinbase/FloatRates.

## 9. CARDAMD

- Internal variants: **8** bank-specific rows, shared `designation_xml=CARDAMD`  
- Strategy: `ONE_CANONICAL_EXPORT_WITH_INTERNAL_VARIANTS`  
- Duplicate prevention: single public CARDAMD identity in XML

## 10. Reserve policy

- Canonical: destination currency effective reserve (`ReserveLinkResolver`) when `type_reserve=0`  
- Quote / order / export aligned for tooling via updated `rates:direction-status`  
- Remaining: raw `direction_reserve=0` is common and must not be read alone

## 11. Catalog health

| Metric | Value |
|---|---|
| active (status=1) | 833 |
| quarantined | 111 |
| deprecated (status=2) | 91 (includes prior batch; cleanup batch was 82) |
| READY_TO_RESTORE | 0 |
| invalid active | 0 |
| mapping drift | 0 |
| health critical | false |

## 12. BestChange package

- `BESTCHANGE_COIN_RUB_FINAL_V2.json`
- `BESTCHANGE_COIN_RUB_FINAL_V2.csv`
- `BESTCHANGE_COIN_RUB_MANUAL_MESSAGE_V2.txt`

## 13. Tests

```bash
php8.4 -l <changed PHP files>   # all OK
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit tests/Unit/Rates --do-not-cache-result
# OK (50 tests, 157 assertions)
sudo nginx -t   # successful
```

## 14. Rollback

```bash
# nginx
sudo cp -a /etc/nginx/fastpanel2-sites/exswaping_co_usr/exswaping.com.conf.bak-xml-timeout-20260720T092621Z \
  /etc/nginx/fastpanel2-sites/exswaping_co_usr/exswaping.com.conf
sudo nginx -t && sudo systemctl reload nginx

# LTC currency
sudo -u app_exswapin_usr mysql ... < ltc_currency_status_rollback.sql
# or: UPDATE currencies SET status=1 WHERE designation_xml='LTC';

# XML last-good
cp -a public/static/exports/currencies.xml.last-good public/static/exports/currencies.xml

# Code: revert release branch commits / restore prior ExportCourses put() behavior
```

## 15. Safety

- no funds moved  
- no real orders created  
- no secrets exposed  
- no guessed mappings  
- no force-push  
- no automatic BestChange contact  
- historical data preserved  
- no unapproved quarantined restores  

## 16. Remaining operator actions

1. Manually submit BestChange V2 package (do not automate).  
2. Optional: renew CMC only if redundancy budget warrants (not required now).  
3. Optional: approve a fresh TON provider later — do not map to GRAM.  
4. Optional: supply verified Payeer BestChange IDs before any public remap.
