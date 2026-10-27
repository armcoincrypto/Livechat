#!/usr/bin/env bash
# Safely set one Fast Market runtime flag in the SHARED .env, then make the
# value effective under Laravel config cache.
#
# - Never sed -i via the LIVE symlink path.
# - Never leave shared .env as root:root 600.
# - After .env write: rebuild or clear bootstrap/cache/config.php so
#   config('fast_market.*') matches the new value (SETTER_CONFIG_CACHE_AWARE).
#
# Usage:
#   set_fast_market_runtime_flag.sh FAST_MARKET_PLANE_ENABLED true
#   set_fast_market_runtime_flag.sh FAST_MARKET_SHADOW_ENABLED false
set -euo pipefail

KEY="${1:-}"
VALUE="${2:-}"
BACKEND_ROOT="${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
SHARED_ENV="${EXSWAPING_BACKEND_SHARED_ENV:-/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/config/.env}"
REPAIR_SYMLINK="${EXSWAPING_REPAIR_ENV_SYMLINK:-0}"
BACKEND_USER="${EXSWAPING_BACKEND_USER:-app_exswapin_usr}"
PHP_BIN="${EXSWAPING_PHP:-/usr/bin/php8.4}"
# Prefer clear (keeps L1 env()-based flags working). Set EXSWAPING_REBUILD_CONFIG_CACHE=1
# to bake via config:cache instead (requires FastMarket via config/fast_market.php).
REBUILD_CACHE="${EXSWAPING_REBUILD_CONFIG_CACHE:-0}"

case "${KEY}" in
  FAST_MARKET_PLANE_ENABLED|FAST_MARKET_SHADOW_ENABLED|FAST_PUBLIC_QUOTE_ENABLED|FAST_ORDER_QUOTE_ENABLED|\
FAST_MARKET_PRODUCER_DAEMON|FAST_MARKET_SCHEDULER_FALLBACK) ;;
  *)
    echo "FAIL unsupported key: ${KEY:-missing}" >&2
    exit 2
    ;;
esac
case "${VALUE}" in
  true|false) ;;
  *)
    echo "FAIL value must be true|false (got ${VALUE:-missing})" >&2
    exit 2
    ;;
esac

[[ -f "${SHARED_ENV}" ]] || { echo "FAIL shared env missing: ${SHARED_ENV}" >&2; exit 2; }
[[ -d "${BACKEND_ROOT}" ]] || { echo "FAIL backend root missing: ${BACKEND_ROOT}" >&2; exit 2; }

live_env="${BACKEND_ROOT}/.env"
if [[ -e "${live_env}" && ! -L "${live_env}" ]]; then
  echo "WARN live .env is a regular file (not symlink to shared): ${live_env}" >&2
  if [[ "${REPAIR_SYMLINK}" == "1" ]]; then
    rm -f "${live_env}"
    ln -s "${SHARED_ENV}" "${live_env}"
    echo "REPAIRED live .env -> ${SHARED_ENV}"
  else
    echo "FAIL refusing to edit while LIVE/.env is release-local; set EXSWAPING_REPAIR_ENV_SYMLINK=1 to restore symlink first" >&2
    exit 3
  fi
fi

if [[ -L "${live_env}" ]]; then
  target="$(readlink -f "${live_env}")"
  shared_real="$(readlink -f "${SHARED_ENV}")"
  if [[ "${target}" != "${shared_real}" ]]; then
    echo "FAIL LIVE/.env symlink target ${target} != shared ${shared_real}" >&2
    exit 3
  fi
fi

owner="$(stat -c '%U' "${SHARED_ENV}")"
group="$(stat -c '%G' "${SHARED_ENV}")"
mode="$(stat -c '%a' "${SHARED_ENV}")"
if [[ "${owner}" == "root" || "${mode}" == "600" ]]; then
  owner="${BACKEND_USER}"
  group="${BACKEND_USER}"
  mode="640"
  echo "WARN repairing shared .env owner/mode -> ${owner}:${group} ${mode}" >&2
fi

tmp="$(mktemp)"
trap 'rm -f "${tmp}"' EXIT
if grep -q "^${KEY}=" "${SHARED_ENV}"; then
  sed "s/^${KEY}=.*/${KEY}=${VALUE}/" "${SHARED_ENV}" > "${tmp}"
else
  cat "${SHARED_ENV}" > "${tmp}"
  printf '\n# Fast market plane runtime flag\n%s=%s\n' "${KEY}" "${VALUE}" >> "${tmp}"
fi
install -o "${owner}" -g "${group}" -m "${mode}" "${tmp}" "${SHARED_ENV}"
echo "SET ${KEY}=${VALUE} shared_env=${SHARED_ENV}"

# Make config('fast_market.*') observe the new value.
if [[ "${REBUILD_CACHE}" == "1" ]]; then
  sudo -u "${BACKEND_USER}" "${PHP_BIN}" "${BACKEND_ROOT}/artisan" config:cache
  echo "CONFIG_CACHE_REBUILT"
else
  sudo -u "${BACKEND_USER}" "${PHP_BIN}" "${BACKEND_ROOT}/artisan" config:clear
  echo "CONFIG_CACHE_CLEARED"
fi

# Assert effective value from application context
map_key() {
  case "$1" in
    FAST_MARKET_PLANE_ENABLED) echo plane_enabled ;;
    FAST_MARKET_SHADOW_ENABLED) echo shadow_enabled ;;
    FAST_PUBLIC_QUOTE_ENABLED) echo public_quote_enabled ;;
    FAST_ORDER_QUOTE_ENABLED) echo order_quote_enabled ;;
    FAST_MARKET_PRODUCER_DAEMON) echo producer_daemon ;;
    FAST_MARKET_SCHEDULER_FALLBACK) echo scheduler_fallback ;;
  esac
}
CFG_KEY="$(map_key "${KEY}")"
EFFECTIVE="$(sudo -u "${BACKEND_USER}" "${PHP_BIN}" -r '
require "'"${BACKEND_ROOT}"'/vendor/autoload.php";
$app=require "'"${BACKEND_ROOT}"'/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$v=config("fast_market.'"${CFG_KEY}"'");
echo $v ? "true" : "false";
')"
echo "EFFECTIVE config(fast_market.${CFG_KEY})=${EFFECTIVE}"
if [[ "${EFFECTIVE}" != "${VALUE}" ]]; then
  echo "FAIL effective config mismatch want=${VALUE} got=${EFFECTIVE}" >&2
  exit 4
fi
echo "EFFECTIVE_ASSERTION=PASS"

# Restart producer unit if present so long-lived loop reloads config
if systemctl list-unit-files exswaping-fast-market-producer.service >/dev/null 2>&1; then
  if systemctl is-enabled --quiet exswaping-fast-market-producer.service 2>/dev/null \
    || systemctl is-active --quiet exswaping-fast-market-producer.service 2>/dev/null; then
    systemctl restart exswaping-fast-market-producer.service || true
    echo "PRODUCER_SERVICE_RESTARTED"
  fi
fi
