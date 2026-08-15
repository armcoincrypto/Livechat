#!/usr/bin/env bash
#
# DEPLOY-HARDEN-1 — read-only Laravel bootstrap / package-cache safety preflight.
#
# Verifies artisan boots, cached package providers are autoloadable, and that
# composer package-discovery would not reintroduce known orphan providers after
# `optimize:clear` / `package:discover`.
#
# Usage (from Laravel app root):
#   bash scripts/ops/verify-laravel-bootstrap-cache-safety.sh
#
# Environment (optional):
#   PHP_BIN   default /usr/bin/php8.4
#
# Exit: 0 = PASS, 1 = FAIL (unsafe for optimize:clear / package:discover / optimize)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"

log() { echo "[bootstrap-cache] $*"; }
pass() { log "PASS $*"; }
fail() { log "FAIL $*"; FAILURES=$((FAILURES + 1)); }

FAILURES=0

if [[ ! -f artisan ]] || [[ ! -f .env ]]; then
  log "FAIL not in Laravel app root (missing artisan or .env)"
  exit 1
fi

if [[ ! -x "$PHP_BIN" ]] && ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  log "FAIL PHP not found: $PHP_BIN"
  exit 1
fi

# --- Artisan bootstrap ---
ARTISAN_VERSION="$("$PHP_BIN" artisan --version 2>/dev/null || true)"
if [[ -z "$ARTISAN_VERSION" ]]; then
  fail "php artisan --version (Laravel bootstrap)"
else
  pass "php artisan --version ($ARTISAN_VERSION)"
fi

PACKAGES_CACHE="bootstrap/cache/packages.php"
SERVICES_CACHE="bootstrap/cache/services.php"

if [[ ! -f "$PACKAGES_CACHE" ]]; then
  fail "bootstrap/cache/packages.php missing (next boot may unsafe-rebuild from installed.json)"
else
  pass "bootstrap/cache/packages.php present"
fi

if [[ ! -f "$SERVICES_CACHE" ]]; then
  fail "bootstrap/cache/services.php missing (next boot will recompile providers)"
else
  pass "bootstrap/cache/services.php present"
fi

# --- Provider autoload + orphan keys in packages.php (PHP read-only) ---
CHECK_JSON="$("$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
if ($root === false) {
    fwrite(STDERR, "no cwd\n");
    exit(2);
}

require $root . '/vendor/autoload.php';

$knownOrphans = [
    'laravel/ai',
    'prism-php/prism',
    'spatie/laravel-sitemap',
];

$result = [
    'packages_cache_exists' => is_file($root . '/bootstrap/cache/packages.php'),
    'orphan_keys_in_packages_cache' => [],
    'missing_providers' => [],
    'provider_count' => 0,
    'installed_orphan_discoverable' => [],
    'dont_discover' => [],
];

$composerPath = $root . '/composer.json';
$required = [];
if (is_file($composerPath)) {
    $composer = json_decode((string) file_get_contents($composerPath), true) ?: [];
    $required = array_merge(
        array_keys($composer['require'] ?? []),
        array_keys($composer['require-dev'] ?? [])
    );
    $result['dont_discover'] = $composer['extra']['laravel']['dont-discover'] ?? [];
}

$packagesCache = $root . '/bootstrap/cache/packages.php';
if (is_file($packagesCache)) {
    /** @var array<string, array<string, mixed>> $packages */
    $packages = require $packagesCache;
    foreach ($knownOrphans as $orphan) {
        if (isset($packages[$orphan])) {
            $result['orphan_keys_in_packages_cache'][] = $orphan;
        }
    }
    foreach ($packages as $pkgName => $cfg) {
        foreach (($cfg['providers'] ?? []) as $provider) {
            $provider = (string) $provider;
            $result['provider_count']++;
            if (! class_exists($provider)) {
                $result['missing_providers'][] = $pkgName . ' => ' . $provider;
            }
        }
    }
}

$installedPath = $root . '/vendor/composer/installed.json';
if (is_file($installedPath)) {
    $installed = json_decode((string) file_get_contents($installedPath), true) ?: [];
    $list = $installed['packages'] ?? $installed;
    foreach ($list as $pkg) {
        $name = (string) ($pkg['name'] ?? '');
        if ($name === '' || in_array($name, $required, true)) {
            continue;
        }
        $extra = $pkg['extra']['laravel'] ?? null;
        if (! is_array($extra) || empty($extra['providers'])) {
            continue;
        }
        if (in_array($name, $knownOrphans, true)) {
            $ignored = in_array($name, $result['dont_discover'], true)
                || in_array('*', $result['dont_discover'], true);
            if (! $ignored) {
                $result['installed_orphan_discoverable'][] = $name;
            }
        }
    }
}

echo json_encode($result, JSON_THROW_ON_ERROR);
PHP
)" || {
  fail "provider autoload inspection (PHP)"
  CHECK_JSON='{}'
}

ORPHAN_KEYS="$(echo "$CHECK_JSON" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo implode(",", $d["orphan_keys_in_packages_cache"] ?? []);')"
MISSING_PROVIDERS="$(echo "$CHECK_JSON" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo count($d["missing_providers"] ?? []);')"
PROVIDER_COUNT="$(echo "$CHECK_JSON" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["provider_count"] ?? 0;')"
INSTALLED_ORPHANS="$(echo "$CHECK_JSON" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo implode(",", $d["installed_orphan_discoverable"] ?? []);')"

if [[ -n "$ORPHAN_KEYS" ]]; then
  fail "known orphan package keys in packages.php: $ORPHAN_KEYS"
else
  pass "no known orphan keys in packages.php"
fi

if [[ "$MISSING_PROVIDERS" != "0" ]]; then
  fail "unautoloadable providers in packages.php ($MISSING_PROVIDERS of $PROVIDER_COUNT)"
  echo "$CHECK_JSON" | "$PHP_BIN" -r '
$d = json_decode(stream_get_contents(STDIN), true);
foreach ($d["missing_providers"] ?? [] as $line) { echo "  - $line\n"; }
'
else
  pass "all cached package providers autoloadable ($PROVIDER_COUNT checked)"
fi

if [[ -n "$INSTALLED_ORPHANS" ]]; then
  fail "installed.json orphan packages would be rediscovered on cache rebuild: $INSTALLED_ORPHANS"
  log "  optimize:clear and package:discover remain UNSAFE until composer.json require/dont-discover/vendor is aligned"
else
  pass "no known orphan discovery hazard in installed.json"
fi

# --- Known orphan provider autoload probe (informational only) ---
ORPHAN_PROBE="$("$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);
require getcwd() . '/vendor/autoload.php';
$classes = [
    'Laravel\\Ai\\AiServiceProvider',
    'Prism\\Prism\\PrismServiceProvider',
    'Spatie\\Sitemap\\SitemapServiceProvider',
];
$missing = [];
foreach ($classes as $class) {
    if (! class_exists($class)) {
        $missing[] = $class;
    }
}
echo json_encode(['missing' => $missing], JSON_THROW_ON_ERROR);
PHP
)" || ORPHAN_PROBE='{"missing":[]}'

ORPHAN_MISSING_COUNT="$(echo "$ORPHAN_PROBE" | "$PHP_BIN" -r 'echo count(json_decode(stream_get_contents(STDIN),true)["missing"] ?? []);')"
if [[ "$ORPHAN_MISSING_COUNT" != "0" ]]; then
  log "INFO known orphan provider classes in vendor/ are not Composer-autoloadable ($ORPHAN_MISSING_COUNT) — composer.json / installed.json drift"
else
  pass "known orphan provider probe (N/A or autoloadable)"
fi

# --- Summary ---
if [[ "$FAILURES" -eq 0 ]]; then
  log "RESULT: PASS (bootstrap cache safe for optimize:clear / package:discover)"
  exit 0
fi

log "RESULT: FAIL ($FAILURES check(s)) — do NOT run artisan optimize, optimize:clear, or package:discover"
exit 1
