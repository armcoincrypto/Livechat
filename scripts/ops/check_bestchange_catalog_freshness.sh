#!/usr/bin/env bash
# Compare local bestchange-codes.json critical IDs against live BestChange catalog.
# Ownership/migration certification aid — not a fail-closed production pricing gate.
set -euo pipefail

LIVE="${EXS_LIVE_APP:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
CODES=""
SOURCE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --live-app) LIVE="$2"; shift 2 ;;
    --codes) CODES="$2"; shift 2 ;;
    --source) SOURCE="$2"; shift 2 ;;
    *) echo "usage: $0 [--live-app PATH] [--codes PATH] [--source WT]" >&2; exit 2 ;;
  esac
done

SOURCE="${SOURCE:-$LIVE}"
CODES="${CODES:-$LIVE/storage/app/bestchange-codes.json}"
CRIT="$SOURCE/resources/rates/bestchange-catalog-critical-codes.json"

[[ -f "$CRIT" ]] || { echo "FAIL missing critical codes policy: $CRIT" >&2; exit 2; }
[[ -f "$CODES" ]] || { echo "FAIL missing local codes: $CODES" >&2; exit 2; }

TMP=$(mktemp /tmp/bc-catalog-freshness.XXXXXX.php)
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
    if (\$loc === null) { \$missingLocal++; \$details[] = ['id'=>\$id,'issue'=>'MISSING_LOCAL_CACHE','expect'=>\$expect,'live'=>\$liveCode,'severity'=>'cache_gap_not_live_defect']; continue; }
    if (\$locCode !== '' && \$locCode !== \$expect) { \$stale++; \$mismatch++; \$details[] = ['id'=>\$id,'issue'=>'STALE_ALIASES','expect'=>\$expect,'local'=>\$locCode,'live'=>\$liveCode]; }
}
echo "BESTCHANGE_CATALOG_STALE_CRITICAL_TOTAL=\$stale\n";
echo "BESTCHANGE_CATALOG_LOCAL_CACHE_GAP_TOTAL=\$missingLocal\n";
echo "BESTCHANGE_CATALOG_MISMATCH_TOTAL=\$mismatch\n";
echo 'DETAILS=' . json_encode(\$details, JSON_UNESCAPED_UNICODE) . "\n";
if (\$stale !== 0) { fwrite(STDERR, "FAIL catalog stale critical=\$stale\n"); exit(1); }
echo "PASS\n";
PHP

sudo -u app_exswapin_usr /usr/bin/php8.4 "$TMP"
