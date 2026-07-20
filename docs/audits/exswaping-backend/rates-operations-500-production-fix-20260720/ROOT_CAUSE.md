# ROOT_CAUSE

## Verdict

**Sustained production HTTP 500** on supported `/rates/operations/{from}/{to}` pairs was caused by:

`RateDirectionEligibility::evaluateDirection()` using column-constrained Eloquent `loadMissing`:

```php
['currency1:id,designation_xml', 'currency2:id,designation_xml']
```

That stripped `id_payment`, poisoned `$currency->payment` as null, and crashed `CurrencyResource` when building the operations payload.

## Details

| Field | Value |
|-------|-------|
| Exception class | PHP Error / trying to use null `payment` during resource transform (surfaced as HTTP 500) |
| Causal file (load) | `app/Services/Rates/RateDirectionEligibility.php` (~line 79 pre-fix) |
| Blow-up file | `packages/ExchangerClient/Http/Resources/Operations/CurrencyResource.php` |
| Gate file | `packages/ExchangerClient/Http/Resources/Operations/DirectionDetailResource.php` |
| Triggering input | Any **found** active direction (e.g. `USDTTRC20/SBERRUB`, `BTC/USDTTRC20`) |
| Why 500 | Serialization assumes `payment` exists; partial load made it null |
| Why blocked pairs did not 500 | `OperationsController::show` returns 404 before resource transform (e.g. dir 268 / SBPRUB not active) |
| Why frontend not responsible | Same API failure on multiple frontend SHAs |

## Contributing incident (earlier)

Deploy of `b6edf3c` (RUB surface gate) without reliable FPM/opcache refresh briefly produced `undefined method evaluateDirection()` + undefined error constant in the resource catch path — also HTTP 500. Resolved by workers picking up the new class; **code fix for the lasting defect is the full currency load**.
