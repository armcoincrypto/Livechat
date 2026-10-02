#!/usr/bin/env bash
# Narrow Spatie permission-cache reset only — never flushes app/session/rate caches.
# Safe post-deploy / ops recovery for TELEGRAM_OPERATOR destination auth drift.
set -euo pipefail

ROOT="${1:-}"
if [[ -z "${ROOT}" ]]; then
  if [[ -f "./artisan" ]]; then
    ROOT="$(pwd)"
  else
    ROOT="$(readlink -f /var/www/app_exswapin_usr/data/www/app.exswaping.com 2>/dev/null || true)"
  fi
fi

if [[ -z "${ROOT}" || ! -f "${ROOT}/artisan" ]]; then
  echo "FAIL missing artisan root" >&2
  exit 2
fi

PHP_BIN="${PHP_BIN:-php8.4}"
if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
  PHP_BIN=php
fi

cd "${ROOT}"
"${PHP_BIN}" artisan permission:cache-reset
echo "PERMISSION_CACHE_RESET_OK root=${ROOT}"
