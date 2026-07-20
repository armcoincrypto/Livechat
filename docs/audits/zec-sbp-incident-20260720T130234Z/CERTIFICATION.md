# ZEC→SBP RATE INCIDENT + ACTIVE CATALOG ECONOMIC VALIDATION

## 1. Final verdict

```text
EXSWAPING_ZEC_SBP_QUARANTINED_PENDING_TRUSTED_BASELINE
```

## 2. Incident answer

| Field | Value |
|---|---|
| Root cause | BestChange-circular pricing for ZEC→RUB with no live independent ZECUSDT feed; prior health used BC peer as baseline (`no_baseline_pass_through`) |
| Direction ID | **541** (ZEC→SBPRUB); also quarantined **540**, **542** |
| Period | Active export until quarantine 2026-07-20 ~13:05Z; XML rate observed 43286–45722 |
| Customer orders | tasks id 3093 only for 541 (2026-04-15, status=3). No evidence of recent completed loss orders in tasks for 541 |
| Financial loss | **Not evidenced** from order history for 541; potential treasury risk prevented by quarantine |

## 3–14

See evidence files in this directory: calculation ledger, economic_audit.json, V3 package, Kate response, rollback SQL.
