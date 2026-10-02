#!/usr/bin/env bash
# Read-only Telegram operator destination authorization health probe.
# Uses artisan telegram:operator-destination-health — no Telegram send, no broad cache flush.
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
command -v "${PHP_BIN}" >/dev/null 2>&1 || PHP_BIN=php

cd "${ROOT}"
out="$("${PHP_BIN}" artisan telegram:operator-destination-health --format=json 2>/dev/null || true)"
if [[ -z "${out}" ]]; then
  echo "telegram_operator_destination_health=unavailable"
  exit 0
fi

python3 - <<'PY' "${out}"
import json,sys
raw=sys.argv[1]
try:
  d=json.loads(raw)
except Exception:
  print("telegram_operator_destination_health=parse_error")
  sys.exit(0)
print(
  "telegram_operator_auth_empty_total={total} state={state} classification={cls} links={links} authorized={auth}".format(
    total=d.get("telegram_operator_auth_empty_total", 0),
    state=d.get("state"),
    cls=d.get("classification"),
    links=d.get("configured_link_count"),
    auth=d.get("authorized_count"),
  )
)
if d.get("classification") == "AUTHORIZATION_EMPTY" and int(d.get("configured_link_count") or 0) > 0:
  sys.exit(1)
sys.exit(0)
PY
