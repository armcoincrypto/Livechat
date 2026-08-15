#!/usr/bin/env bash
#
# DEPLOY-GATE-1 — read-only production schema smoke check (Support Chat contract).
#
# Verifies Laravel bootstrap, required Support Chat columns/indexes, and no pending
# migrations in database/migrations/11.0.5/. Does not mutate the database.
#
# Usage (from Laravel app root):
#   bash scripts/ops/verify-production-schema-readiness.sh
#
# Environment (optional):
#   PHP_BIN              default /usr/bin/php8.4
#   MIGRATIONS_VERSION   default 11.0.5
#   SKIP_PENDING_MIGRATIONS=1   skip pending-migration scan
#
# Exit: 0 = PASS, 1 = FAIL
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
MIGRATIONS_VERSION="${MIGRATIONS_VERSION:-11.0.5}"
MIGRATIONS_PATH="database/migrations/${MIGRATIONS_VERSION}"

log() { echo "[schema-readiness] $*"; }
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

# --- Laravel bootstrap + schema contract (SupportChatSchemaReadinessService) ---
SCHEMA_JSON="$("$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
if ($root === false || ! is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "bootstrap_error: vendor/autoload.php missing\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $assess = app(\iEXPackages\SupportChat\Services\SupportChatSchemaReadinessService::class)->assess();
    echo json_encode($assess, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'bootstrap_error: ' . $e->getMessage() . "\n");
    exit(2);
}
PHP
)" || {
  log "FAIL Laravel bootstrap or schema assessment"
  exit 1
}

pass "Laravel bootstrap OK"

READY="$(echo "$SCHEMA_JSON" | "$PHP_BIN" -r 'echo json_decode(stream_get_contents(STDIN), true)["support_chat_schema_ready"] ? "yes" : "no";')"
MISSING_COLS="$(echo "$SCHEMA_JSON" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo empty($d["missing_columns"])?"":implode(", ",$d["missing_columns"]);')"
MISSING_IDX="$(echo "$SCHEMA_JSON" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo empty($d["missing_indexes"])?"":implode(", ",$d["missing_indexes"]);')"

if [[ "$READY" == "yes" ]]; then
  pass "Support Chat schema contract (columns + indexes)"
else
  fail "Support Chat schema contract"
  [[ -n "$MISSING_COLS" ]] && log "  missing columns: $MISSING_COLS"
  [[ -n "$MISSING_IDX" ]] && log "  missing indexes: $MISSING_IDX"
fi

# --- Pending migrations in version folder (read-only) ---
if [[ "${SKIP_PENDING_MIGRATIONS:-0}" != "1" ]]; then
  if [[ ! -d "$MIGRATIONS_PATH" ]]; then
    fail "migrations path missing: $MIGRATIONS_PATH"
  else
    PENDING_OUT="$("$PHP_BIN" <<PHP
<?php
declare(strict_types=1);

\$root = getcwd();
require \$root . '/vendor/autoload.php';
\$app = require \$root . '/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\$version = getenv('MIGRATIONS_VERSION') ?: '11.0.5';
\$dir = \$root . '/database/migrations/' . \$version;
\$files = [];
foreach (glob(\$dir . '/*.php') ?: [] as \$path) {
    \$files[] = pathinfo(\$path, PATHINFO_FILENAME);
}
sort(\$files);

\$applied = Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all();
\$appliedSet = array_flip(\$applied);

\$pending = [];
foreach (\$files as \$stem) {
    if (! isset(\$appliedSet[\$stem])) {
        \$pending[] = \$stem;
    }
}

echo json_encode(['pending' => \$pending, 'version' => \$version], JSON_THROW_ON_ERROR);
PHP
)"
    PENDING_COUNT="$(echo "$PENDING_OUT" | "$PHP_BIN" -r 'echo count(json_decode(stream_get_contents(STDIN),true)["pending"]);')"

    if [[ "$PENDING_COUNT" == "0" ]]; then
      pass "No pending migrations in $MIGRATIONS_PATH"
    else
      fail "Pending migrations in $MIGRATIONS_PATH ($PENDING_COUNT)"
      echo "$PENDING_OUT" | "$PHP_BIN" -r '
$d = json_decode(stream_get_contents(STDIN), true);
foreach ($d["pending"] as $m) { echo "  - $m\n"; }
'
    fi
  fi
else
  log "SKIP pending migration scan (SKIP_PENDING_MIGRATIONS=1)"
fi

# --- Summary ---
if [[ "$FAILURES" -eq 0 ]]; then
  log "RESULT: PASS (schema ready for Support Chat telemetry deploy)"
  exit 0
fi

log "RESULT: FAIL ($FAILURES check(s) failed) — do not deploy Support Chat code until schema is aligned"
exit 1
