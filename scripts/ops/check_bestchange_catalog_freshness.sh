#!/usr/bin/env bash
# Compare local bestchange-codes.json critical IDs against live BestChange catalog.
# Ownership/migration certification aid — not a fail-closed production pricing gate
# unless --migration-cert is set (then local gaps / rail mismatches fail closed).
set -euo pipefail

LIVE="${EXS_LIVE_APP:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
CODES=""
SOURCE=""
MIGRATION_CERT=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --live-app) LIVE="$2"; shift 2 ;;
    --codes) CODES="$2"; shift 2 ;;
    --source) SOURCE="$2"; shift 2 ;;
    --migration-cert) MIGRATION_CERT=1; shift ;;
    *) echo "usage: $0 [--live-app PATH] [--codes PATH] [--source WT] [--migration-cert]" >&2; exit 2 ;;
  esac
done

SOURCE="${SOURCE:-$LIVE}"
CODES="${CODES:-$LIVE/storage/app/bestchange-codes.json}"
CRIT="$SOURCE/resources/rates/bestchange-catalog-critical-codes.json"

[[ -f "$CRIT" ]] || { echo "FAIL missing critical codes policy: $CRIT" >&2; exit 2; }
[[ -f "$CODES" ]] || { echo "FAIL missing local codes: $CODES" >&2; exit 2; }

# Durable executable bit for the helper itself (release/ops artifact).
if [[ ! -x "$0" ]]; then
  echo "WARN helper not executable: $0" >&2
fi

TMP=$(mktemp /tmp/bc-catalog-freshness.XXXXXX.php)
# Root-created tmp must be world-readable so app_exswapin_usr can open it.
chmod 644 "$TMP"
trap 'rm -f "$TMP"' EXIT
cat >"$TMP" <<PHP
<?php
require '$LIVE/vendor/autoload.php';
\$app = require '$LIVE/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$crit = json_decode((string) file_get_contents('$CRIT'), true);
\$local = json_decode((string) file_get_contents('$CODES'), true) ?: [];
\$http = app(iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface::class);
\$curr = \$http->fetchCurrencies('en');
\$byId = [];
foreach (\$curr as \$k => \$v) {
    if (is_array(\$v)) { \$byId[(int)(\$v['id'] ?? \$k)] = \$v; }
}
\$stale = 0; \$missingLocal = 0; \$mismatch = 0; \$details = [];
foreach ((\$crit['critical_codes'] ?? []) as \$id => \$expect) {
    \$id = (int) \$id;
    \$expect = strtoupper((string) \$expect);
    \$live = \$byId[\$id] ?? null;
    \$loc = \$local[(string)\$id] ?? \$local[\$id] ?? null;
    \$liveCode = strtoupper((string)(\$live['code'] ?? ''));
    \$locCode = is_array(\$loc) ? strtoupper((string)(\$loc['code'] ?? '')) : '';
    if (\$live === null) { \$stale++; \$mismatch++; \$details[] = ['id'=>\$id,'issue'=>'MISSING_LIVE_CODES','expect'=>\$expect]; continue; }
    if (\$liveCode !== \$expect) { \$stale++; \$mismatch++; \$details[] = ['id'=>\$id,'issue'=>'CODE_LABEL_MISMATCH','expect'=>\$expect,'live'=>\$liveCode]; continue; }
    if (\$loc === null) { \$missingLocal++; \$details[] = ['id'=>\$id,'issue'=>'MISSING_LOCAL_CACHE','expect'=>\$expect,'live'=>\$liveCode,'severity'=>'cache_gap']; continue; }
    if (\$locCode !== '' && \$locCode !== \$expect) { \$stale++; \$mismatch++; \$details[] = ['id'=>\$id,'issue'=>'STALE_ALIASES','expect'=>\$expect,'local'=>\$locCode,'live'=>\$liveCode]; }
}
echo "BESTCHANGE_CATALOG_STALE_CRITICAL_TOTAL=\$stale\n";
echo "LOCAL_CATALOG_MISSING_CRITICAL=\$missingLocal\n";
echo "CODE_LABEL_MISMATCH_CRITICAL=\$mismatch\n";
echo "BESTCHANGE_CATALOG_LOCAL_CACHE_GAP_TOTAL=\$missingLocal\n";
echo "BESTCHANGE_CATALOG_MISMATCH_TOTAL=\$mismatch\n";
echo 'DETAILS=' . json_encode(\$details, JSON_UNESCAPED_UNICODE) . "\n";
\$migrationCert = $MIGRATION_CERT;
if (\$stale !== 0) { fwrite(STDERR, "FAIL catalog stale critical=\$stale\n"); exit(1); }
if (\$migrationCert && \$missingLocal !== 0) {
    fwrite(STDERR, "FAIL LOCAL_CATALOG_MISSING_CRITICAL=\$missingLocal (migration-cert)\n");
    exit(1);
}
echo "PASS\n";
PHP
chmod 644 "$TMP"

sudo -u app_exswapin_usr /usr/bin/php8.4 "$TMP"
