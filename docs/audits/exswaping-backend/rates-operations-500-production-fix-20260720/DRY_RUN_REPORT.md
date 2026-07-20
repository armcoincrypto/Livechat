# DRY_RUN_REPORT

## Method

Bootstrapped Laravel via `/usr/bin/php8.4 -r` (no tinker; tinker not registered in this app).

Invoked `RateDirectionEligibility::make()->evaluateDirection($direction)` only.

No `Order` create, no merchant payout, no DB writes.

## Results

```text
PAIR=USDTTRC20->SBERRUB ID=15 quote=1 order=1 export=1 BC=1 id_payment1=6 payment1=6
PAIR=BTC->USDTTRC20 ID=8 quote=1 order=1 export=1 BC=1 id_payment1=1 payment1=1
PAIR=USDTTRC20->SBPRUB ID=268 quote=0 order=0 export=0 BC=0
DIR268 … reasons=[quarantined…, direction_not_active, … rub_family_quarantine_required]
```

```text
ORDERS_CREATED=0
FUNDS_MOVED=0
DATABASE_WRITES=0
```
