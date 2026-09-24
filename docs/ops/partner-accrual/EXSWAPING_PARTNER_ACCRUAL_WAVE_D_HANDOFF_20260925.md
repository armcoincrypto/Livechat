# Partner Accrual — Wave D release inventory (source handoff)

## Lineage

```text
LIVE_BACKEND_SHA=acc33237d9f516f3b75f885bc28abe7dc7828041
LIVE_FRONTEND_SHA=64af30e5c8ac1c89fe4c5bed79c680ad361a1cca
WAVE_A_SHA=533992c2932328e56a26797786a6e97eb51519dc
WAVE_B_SHA=92b738bda05e3576306df39c3e635d09587dbc34
WAVE_C_BACKEND_BRANCH=feature/exswaping-partner-accrual-wave-c
WAVE_C_FRONTEND_BRANCH=feature/exswaping-partner-accrual-wave-c-frontend
```

## Migrations (additive)

```text
database/migrations/11.0.5/2026_09_24_120000_create_partner_accrual_tables.php
database/migrations/11.0.5/2026_09_24_140000_partner_accrual_wave_b_schedule_columns.php
database/migrations/11.0.5/2026_09_25_100000_seed_partner_accrual_permissions.php
```

## Config flags (default OFF)

```text
PARTNER_ACCRUAL_ENABLED=false
PARTNER_ACCRUAL_SCHEDULER_ENABLED=false
PARTNER_ACCRUAL_ALLOW_APPLY=false
config/partner_accrual.php
```

## Permissions

```text
admin_partner_accrual_view
admin_partner_accrual_manage
(+ admin_users remains valid super-set)
```

## Admin routes

```text
GET  /account/users/{id}/partner-accrual
POST /account/users/{id}/partner-accrual/preview
POST /account/users/{id}/partner-accrual
GET  /account/users/partner-accrual/plans/{planId}
POST /account/users/partner-accrual/plans/{planId}/pause|resume|cancel
GET  /account/users/{id}/partner-accrual/dry-run
```

## Partner routes

```text
GET /apis/client-api/v1/account/partners  (+ monthly_accrual block)
GET /apis/client-api/v1/account/partners/accrual-history
```

## Admin UI

```text
public/static/admin-partner-accrual-panel.js
resources/views/admin-vue.blade.php (script include)
```

## Frontend

```text
src/components/account/AccountPartners.tsx
src/lib/account/accountPartnersTypes.ts
src/lib/i18n.ts
```

## Wave D must

1. Forward-integrate onto newest live backend+frontend
2. Migrate DB (additive)
3. Assign permissions to operator roles
4. Enable flags only after QA dry-run
5. Never enable scheduler until certified
