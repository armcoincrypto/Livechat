# IMPLEMENTATION_REPORT

## Minimal fix

In `RateDirectionEligibility::evaluateDirection()`:

```diff
- $direction->loadMissing(['currency1:id,designation_xml', 'currency2:id,designation_xml']);
+ $direction->loadMissing(['currency1', 'currency2']);
```

Comment documents why partial select is unsafe for public operations serialization.

## Regression test

`tests/Unit/Rates/RateDirectionEligibilityCurrencyLoadTest.php`

1. Asserts `id_payment` and `payment` survive `evaluateDirection`.
2. Documents that constrained load poisons `payment`.

## What was not changed

- Frontend calculator / redesign
- RUB policy thresholds
- Sitemap eligibility lists
- Order/webhook/KYC flows
- Broad catch-all empty success responses
- Database rows / rates

## Commit

```text
c2c3e1464e881929475182d23b49eb787383b815
fix(rates): keep full currency rows in eligibility loadMissing
```
