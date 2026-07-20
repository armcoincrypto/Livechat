# ENDPOINT_EXECUTION_PATH

```text
GET /apis/client-api/v1/rates/operations/{buy}/{sell}
  → Nginx (exswaping.com) → fastcgi unix:/var/run/app.exswaping.com.sock (php8.4 pool app.exswaping.com)
  → Laravel route: client-api/v1/rates/operations/{buy}/{sell}
  → iEXPackages\ExchangerClient\Http\Controller\Operations\OperationsController::show
       → findActiveDirection(buy, sell)
            status=1 + currency status=0
            match by currency IDs or designation_xml (formatted_permitted_codes)
       → if null: HTTP 404 JSON { errors: [ Not Found ] }
       → else: DirectionDetailResource($item)
            → RateDirectionEligibility::make()->evaluateDirection($direction)
                 loadMissing currency1/currency2 (FULL rows — post-fix)
                 RubFamilyPremiumPolicy for crypto→RUB
                 quote_allowed / order_allowed / export_allowed / BestChange_allowed
            → if quote_allowed: CalculatorFacade::setDirectionExchange()->calculate()
            → else: rate_unavailable + ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE
            → CurrencyResource(currency1/currency2) requires payment relation (id_payment)
            → JSON attributes: course, limits, currencies, fees, seo, …
```

## Canonical policy reuse

No parallel eligibility for this endpoint — quote gate uses the same `RateDirectionEligibility::evaluateDirection()` as order validation and XML/BestChange export helpers.
