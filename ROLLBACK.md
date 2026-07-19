# Rollback — rate pipeline post-fix remediation 2026-07-19

## Code backups
- `/var/www/app_exswapin_usr/data/www/app.exswaping.com/backups/rate-pipeline-20260719T160827Z` (original +3% mutator fix)
- `/var/www/app_exswapin_usr/data/www/app.exswaping.com/backups/rate-pipeline-postfix-20260719T163025Z` (this phase)

## Restore code
```bash
APP=/var/www/app_exswapin_usr/data/www/app.exswaping.com
BK=$APP/backups/rate-pipeline-postfix-20260719T163025Z
cp -a "$BK/packages/BestChange/Services/RatesUpdateService.php" "$APP/packages/BestChange/Services/RatesUpdateService.php"
cp -a "$BK/packages/Courses/Export/Concerns/ExportFormatHelpers.php" "$APP/packages/Courses/Export/Concerns/ExportFormatHelpers.php"
cp -a "$BK/app/Console/Commands/RatesAuditCommand.php" "$APP/app/Console/Commands/RatesAuditCommand.php"
# optional: remove new service files under app/Services/Rates/ except RateSanityGuard.php
sudo -u app_exswapin_usr php8.4 artisan scheme:files
sudo -u app_exswapin_usr python3 $APP/xml-changer/main.py
```

## Restore quarantined directions
```bash
# Apply newest rollback SQL written under storage/app/rate-quarantine-rollback-*.sql
# in reverse chronological order (batch3, batch2, then first PRUSD/zeros batch).
```

No PM2/frontend/nginx restart required.
