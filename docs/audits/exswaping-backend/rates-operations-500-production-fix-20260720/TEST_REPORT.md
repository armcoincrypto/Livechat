# TEST_REPORT

## Commands

```bash
/usr/bin/php8.4 -l app/Services/Rates/RateDirectionEligibility.php
/usr/bin/php8.4 -l tests/Unit/Rates/RateDirectionEligibilityCurrencyLoadTest.php

/usr/bin/php8.4 vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php \
  tests/Unit/Rates/RateDirectionEligibilityCurrencyLoadTest.php
# → OK (2 tests, 9 assertions)

/usr/bin/php8.4 vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/Unit/Rates/
# → OK (78 tests, 235 assertions)

/usr/bin/php8.4 vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php \
  tests/Unit/Rates/RubFamilyPremiumPolicyTest.php \
  tests/Unit/Rates/RatePublicSurfaceGateTest.php \
  tests/Unit/UpdateSitemapCommandTest.php
# → OK (31 tests, 3458 assertions)

/usr/bin/php8.4 artisan route:list --path=rates/operations
# → GET client-api/v1/rates/operations/{buy}/{sell}
```

## Note

`php artisan test --filter=…` and bare `vendor/bin/phpunit` against the full Unit suite can execute procedural `tests/Unit/ReferralCaptureResolverTest.php` (exits early). Prefer path-scoped PHPUnit as above.

## Syntax

Both modified PHP files: `No syntax errors detected`.
