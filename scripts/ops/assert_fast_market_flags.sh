#!/usr/bin/env bash
# Assert effective FastMarket flags from a bootstrapped Laravel app
# (works with or without config:cache).
set -euo pipefail

BACKEND_ROOT="${1:-${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}}"
PHP_BIN="${EXSWAPING_PHP:-/usr/bin/php8.4}"
BACKEND_USER="${EXSWAPING_BACKEND_USER:-app_exswapin_usr}"

sudo -u "${BACKEND_USER}" "${PHP_BIN}" -r '
require "'"${BACKEND_ROOT}"'/vendor/autoload.php";
$app=require "'"${BACKEND_ROOT}"'/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\Rates\FastMarket\FastMarketFlags;
$cached=$app->configurationIsCached();
echo "config_cached=".json_encode($cached)."\n";
echo "plane=".json_encode(FastMarketFlags::planeEnabled())."\n";
echo "shadow=".json_encode(FastMarketFlags::shadowEnabled())."\n";
echo "public=".json_encode(FastMarketFlags::publicQuoteEnabled())."\n";
echo "order=".json_encode(FastMarketFlags::orderQuoteEnabled())."\n";
echo "producer_daemon=".json_encode(FastMarketFlags::producerDaemonPreferred())."\n";
echo "scheduler_fallback=".json_encode(FastMarketFlags::schedulerFallbackEnabled())."\n";
echo "scheduler_admitted=".json_encode(FastMarketFlags::schedulerRefreshAdmitted())."\n";
echo "cadence=".FastMarketFlags::snapshotRefreshSeconds()."\n";
echo "fresh=".FastMarketFlags::freshnessThresholdSeconds()."\n";
echo "grace=".FastMarketFlags::graceThresholdSeconds()."\n";
echo "expired=".FastMarketFlags::expiredThresholdSeconds()."\n";
// Prove no silent false from cache when .env says true: compare env file if present
$envFile=base_path(".env");
if (is_link($envFile) || is_file($envFile)) {
  $raw=@file_get_contents($envFile) ?: "";
  preg_match("/^FAST_MARKET_PLANE_ENABLED=(.*)$/m", $raw, $m);
  $want=isset($m[1]) ? filter_var(trim($m[1]), FILTER_VALIDATE_BOOLEAN) : false;
  $got=FastMarketFlags::planeEnabled();
  if ($want !== $got) {
    fwrite(STDERR, "FAIL plane env/file={$want} effective={$got} (config_cached=".json_encode($cached).")\n");
    exit(4);
  }
}
echo "EFFECTIVE_ASSERTION_CONFIG_CACHE_AWARE=PASS\n";
'
