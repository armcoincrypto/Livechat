#!/usr/bin/env bash
# Read-only BestChange refresh watchdog (no mutex force-release, no kill).
# Invokes artisan rates:bestchange-dispatch-watch when available on the live backend.
set -euo pipefail
BACKEND_ROOT="${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
PHP_BIN="${EXSWAPING_PHP_BIN:-/usr/bin/php8.4}"
LOG_DIR="${EXSWAPING_BC_WATCH_LOG_DIR:-/var/lib/exswaping-owned/pricing/authority-drift}"
mkdir -p "${LOG_DIR}"

if [[ ! -f "${BACKEND_ROOT}/artisan" ]]; then
  echo "FAIL missing artisan at ${BACKEND_ROOT}" >&2
  exit 2
fi

# Prefer app user for DB/redis credentials.
if [[ "$(id -un)" == "app_exswapin_usr" ]]; then
  "${PHP_BIN}" "${BACKEND_ROOT}/artisan" rates:bestchange-dispatch-watch --format=json \
    | tee -a "${LOG_DIR}/bestchange_dispatch_watch.watchdog.jsonl"
else
  sudo -u app_exswapin_usr -H -- "${PHP_BIN}" "${BACKEND_ROOT}/artisan" rates:bestchange-dispatch-watch --format=json \
    | tee -a "${LOG_DIR}/bestchange_dispatch_watch.watchdog.jsonl"
fi
