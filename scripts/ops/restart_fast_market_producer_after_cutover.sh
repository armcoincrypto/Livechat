#!/usr/bin/env bash
# Restart long-lived fast-market producer after backend cutover/rollback and
# verify the running process resolves to the current LIVE release SHA.
#
# Usage:
#   restart_fast_market_producer_after_cutover.sh [expected-sha]
set -euo pipefail

LIVE="${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
UNIT="${EXSWAPING_FAST_MARKET_PRODUCER_UNIT:-exswaping-fast-market-producer}"
EXPECTED_SHA="${1:-}"

if [[ -z "${EXPECTED_SHA}" && -f "${LIVE}/RELEASE.json" ]]; then
  EXPECTED_SHA="$(python3 -c "import json; d=json.load(open('${LIVE}/RELEASE.json')); print(d.get('git_sha') or d.get('RELEASE_SHA') or '')")"
fi

if ! systemctl cat "${UNIT}" >/dev/null 2>&1; then
  echo "FAST_PRODUCER_RESTART=SKIP unit_missing=${UNIT}"
  exit 0
fi

systemctl restart "${UNIT}"
sleep 1
active="$(systemctl is-active "${UNIT}" || true)"
pid="$(systemctl show -p MainPID --value "${UNIT}" || echo 0)"
echo "FAST_PRODUCER_RESTART active=${active} pid=${pid}"

if [[ "${active}" != "active" || -z "${pid}" || "${pid}" == "0" ]]; then
  echo "FAIL fast-market producer not active after restart" >&2
  exit 20
fi

cwd="$(readlink -f "/proc/${pid}/cwd" 2>/dev/null || true)"
live_target="$(readlink -f "${LIVE}")"
producer_sha=""
if [[ -f "${cwd}/RELEASE.json" ]]; then
  producer_sha="$(python3 -c "import json; print(json.load(open('${cwd}/RELEASE.json')).get('git_sha') or '')")"
fi

echo "PRODUCER_PID=${pid}"
echo "PRODUCER_CWD=${cwd}"
echo "LIVE_TARGET=${live_target}"
echo "PRODUCER_CODE_SHA=${producer_sha}"
echo "LIVE_BACKEND_SHA=${EXPECTED_SHA}"

if [[ -n "${EXPECTED_SHA}" && -n "${producer_sha}" && "${producer_sha}" != "${EXPECTED_SHA}" ]]; then
  echo "FAIL PRODUCER_CODE_SHA!=LIVE_BACKEND_SHA" >&2
  exit 21
fi
if [[ -n "${cwd}" && -n "${live_target}" && "${cwd}" != "${live_target}" ]]; then
  echo "FAIL producer cwd not LIVE release tree" >&2
  exit 21
fi

echo "PRODUCER_LIVE_SHA_MATCH=YES"
echo "CUTOVER_RESTARTS_FAST_PRODUCER=YES"
