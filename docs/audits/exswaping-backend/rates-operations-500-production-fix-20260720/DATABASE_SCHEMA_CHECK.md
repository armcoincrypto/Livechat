# DATABASE_SCHEMA_CHECK

Read-only checks only. No row updates.

## Findings

| Check | Result |
|-------|--------|
| Direction rows for supported pairs | Present (e.g. id 15 USDTTRC20→SBERRUB, id 8 BTC→USDTTRC20) |
| Direction 268 USDTTRC20→SBPRUB | Present; **not** returned by `findActiveDirection` (inactive / policy) |
| Currency `id_payment` | Present on full row loads; **missing** under constrained `loadMissing` (documented in unit test) |
| Migrations | No new migration required for this fix |
| Schema drift | None for this incident — defect was application load shape, not missing columns |

## Direction 268 dry-run eligibility

```text
DIR268 USDTTRC20→SBPRUB
quote=0 order=0 export=0 BestChange=0
reasons include: direction_not_active, export_hard_disabled, rub_family_quarantine_required, …
```
