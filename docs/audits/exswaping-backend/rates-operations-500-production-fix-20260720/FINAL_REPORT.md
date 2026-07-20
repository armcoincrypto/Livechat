# FINAL_REPORT

## Verdict

```text
EXSWAPING_RATES_OPERATIONS_500_PRODUCTION_FIX_PASS_WITH_WARNINGS
```

Warnings: (1) origin/main may still lag local tip until push; (2) malformed asset IDs can return HTTP 200 with `code:-1` Not Found (pre-existing contract quirk, not 500); (3) live checkout branch name is the fix branch while `main` tip shares the same SHA.

## Main-goal result

Public calculator quote **operations load again** (HTTP 200 + course + currency metadata) on production apex API. Frontend redesign unchanged.

## Root cause (summary)

Partial `loadMissing(['currency1:id,designation_xml', …])` in `RateDirectionEligibility::evaluateDirection` stripped `id_payment`, nullified `payment`, crashed `CurrencyResource` → HTTP 500 for every found direction. Frontend release not causal.

## Success criteria checklist

| Criterion | Status |
|-----------|--------|
| Supported pair works | PASS |
| Blocked pair remains blocked | PASS (404 / quote=0) |
| Unsupported fails safely | PASS (404) |
| No HTTP 500 on matrix | PASS |
| Frontend calculator recovers | PASS (API + redesign marker) |
| No orders/funds in validation | PASS |
| No policy regression | PASS |

## Audit location

`docs/audits/exswaping-backend/rates-operations-500-production-fix-20260720/`
