# Canonical rate path (full ownership map)

Sources: live audit + [Map canonical rate path](10b9bd27-2c32-4870-8217-8f546f6fbd69) / [Audit parser provider coverage](0452dcf3-4fc7-477b-8b6c-d9bfb9e0f225).

## Write path (stored `course_value`)

```text
compiler:bestchange
  → BestChangeHttpClient / RatesUpdateService
  → BestChangeMarketPipeline + RateCalculator (invert + step)
  → RateSanityGuard / PeerRateSelector (BC peer clamp)
  → bestchange_directions.rate_value
  → DirectionExchangeRecalculateService + Calculator (+ direction.profit)
  → direction_exchange.course_value
```

Parallel: `compiler:courses` / `CompilerDefaultService` writes `parser_exchange.summa` (independent feeds).

## Public XML

```text
course_value
  → shouldExportRate (mapping + RateExportQuarantine + RubFamilyPremiumPolicy gate)
  → calculateCourseValues / roundBcmath
  → AtomicPublicXmlPublisher → public/static/exports/currencies.xml
  → xml-changer (URLs only; rate mutate OFF by default)
```

## API / order

Live `CalculatorFacade` / `OrderCalculator` — **does not** call `RubFamilyPremiumPolicy`.
Orderability currently hinges on `direction.status=1` (and related active scopes).
Family quarantine for orders requires DB quarantine or a future order-path gate (DEF-003).

## Independent baseline (gate / audit only — never BestChange mid)

| Path | Formula |
|---|---|
| `stable_to_rub` | USDRUB (CBR) |
| `crypto_to_rub` | ASSETUSDT × USDRUB |
| `crypto_to_gel` | ASSETUSDT × USDGEL |
| `*_via_usdt` | crypto/stable bridges |

Owner: `IndependentMarketBaseline` + `PeerRateSelector` over `parser_exchange` status=1, age-gated.

## Classification of alternate paths

| Path | Class |
|---|---|
| ionCube `Rates` / `RatesConnection` | LEGACY_ACTIVE |
| Calculator file/competitor/formula/manual/crypto fallbacks | LEGACY_ACTIVE |
| `xml-changer` rate bump env | LEGACY_DISABLED (dangerous if enabled) |
| `RateDirectionEligibility` | DIAGNOSTIC (+ family overlay on direction-status) |
| `RubFamilyPremiumPolicy` / baseline | GATE / DIAGNOSTIC (not writers) |
| `ExportCourses::clear` live truncate | LEGACY_DISABLED |

## Dangerous mutators

- `EXSWAPING_XML_CHANGER_MUTATE_RATES=1` — bypasses all rate guards
- Order/API live Calculator without family gate (DEF-003)
- Manual strategy if selected in calculator priority
