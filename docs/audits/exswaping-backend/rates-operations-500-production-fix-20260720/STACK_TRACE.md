# STACK_TRACE

## Primary failure sequence (production log `storage/logs/iex-2026-07-20.log`)

### Phase A — stale class / incomplete surface (earlier window ~20:31–20:32)

```text
rate_public_surface_quote_gate_failed
  message: Call to undefined method App\Services\Rates\RateDirectionEligibility::evaluateDirection()

then Error:
  Undefined constant App\Services\Rates\RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE
  at packages/ExchangerClient/Http/Resources/Operations/DirectionDetailResource.php:58
```

Cause: `b6edf3c` added `DirectionDetailResource` calls to `evaluateDirection()` / error constant while FPM workers still executed an older in-memory class definition without those members. Catch block then referenced the missing constant → uncaught `Error` → HTTP 500.

### Phase B — lasting code defect (after method available)

Once `evaluateDirection()` ran:

```php
$direction->loadMissing(['currency1:id,designation_xml', 'currency2:id,designation_xml']);
```

Eloquent partial select **stripped `id_payment`**, so `$currency->payment` resolved as null.  
`CurrencyResource` then accessed `$this->payment->logo` → fatal → HTTP 500 on every successful direction lookup that reached serialization.

## Exact exception class (Phase B root cause for sustained 500)

| Item | Value |
|------|-------|
| Trigger | Supported pair lookup that returns a DirectionExchange |
| Mechanism | Partial `loadMissing` poisons `payment` relation |
| Blow-up site | `packages/ExchangerClient/Http/Resources/Operations/CurrencyResource.php` (uses `$this->payment->logo`) |
| Gate site | `DirectionDetailResource.php` lines 44–58 (`evaluateDirection` then CalculatorFacade) |

## Why frontend SHA was not responsible

Same 500 on redesigned production (`7414aef`), prior frontend (`99bda19`), and preview — all call the same Laravel `/apis/client-api/v1/rates/operations/{from}/{to}` surface.
