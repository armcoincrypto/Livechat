# Test results

## PHP syntax

```bash
php8.4 -l app/Console/Commands/RatesDeployVerifyCommand.php
php8.4 -l app/Services/Rates/RateDirectionEligibility.php
php8.4 -l packages/Courses/Export/Concerns/ExportFormatHelpers.php
php8.4 -l tests/Unit/Rates/RatePublicSurfaceGateTest.php
php8.4 -l tests/Unit/Rates/BestChangePublicIdentityCanonicalizationTest.php
```

Result: all five files reported no syntax errors.

## Rate tests

```bash
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit \
  tests/Unit/Rates \
  --do-not-cache-result
```

Result: `86 tests, 267 assertions, 0 failures, 0 errors`; one pre-existing PHPUnit deprecation was reported.

Focused worktree result: `12 tests, 42 assertions, 0 failures, 0 errors`.

## Nginx

```bash
sudo nginx -t
```

Result: syntax OK and configuration test successful. Existing map-hash, duplicate MIME, and OCSP warnings remain unchanged.

## Public surfaces

- Direction `128` (`DASH→SBPRUB`): API returns `rate_unavailable=true`, `DIRECTION_TEMPORARILY_UNAVAILABLE`; quote/order/export/BestChange all false.
- Direction `15` (`USDTTRC20→SBERRUB`): API course present; quote/order/export/BestChange all true.
- Direction `540` remains quarantined and absent from public eligibility.
- No customer order was created.

## XML

- Two explicit `scheme:files` cycles completed.
- 20/20 public requests returned non-empty valid XML.
- Timeouts: `0`; zero-byte responses: `0`; item count: stable at `740`.
- Hash changed during the probe because the scheduled rate generator published a fresh rate snapshot; structure and item count remained stable.

