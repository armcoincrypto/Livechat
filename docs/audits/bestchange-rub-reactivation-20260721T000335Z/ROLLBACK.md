# Rollback

No database rows, mappings, reserve records, orders, or currency flags were changed.

## RUB eligibility and CARDAMD export

Before merge, restore the production worktree to the frozen production SHA:

```bash
cd /var/www/app_exswapin_usr/data/www/app.exswaping.com
git restore --source=b12cbbd68f2d33bfd4e667dbb7478c812ed53766 --worktree -- \
  app/Services/Rates/RateDirectionEligibility.php \
  packages/Courses/Export/Concerns/ExportFormatHelpers.php \
  app/Console/Commands/RatesDeployVerifyCommand.php \
  tests/Unit/Rates/RatePublicSurfaceGateTest.php
rm -f tests/Unit/Rates/BestChangePublicIdentityCanonicalizationTest.php
sudo -u app_exswapin_usr php8.4 artisan optimize:clear
sudo -u app_exswapin_usr php8.4 artisan scheme:files
```

After merge, deploy the previous main commit through the normal non-force deployment process, clear Laravel caches, and run `scheme:files`.

## XML

XML rows were never edited manually. `scheme:files` atomically regenerates the feed from code and database state. The publisher also keeps `public/static/exports/currencies.xml.last-good`; use it only as a short emergency delivery fallback, then regenerate with the selected code release.

## Database flags

No rollback SQL is required: database mutations = `0`.

