#!/usr/bin/env bash
# Safely set one L1 runtime flag in the SHARED .env only.
# - Never sed -i via the LIVE symlink path (GNU sed replaces symlinks with files).
# - Never sed -i as root on SHARED (replaces inode as root:root and can make .env
#   unreadable to app_exswapin_usr, so Dotenv loads nothing and flags default false).
# - Writes via temp + install preserving owner/mode.
#
# Usage:
#   set_l1_runtime_flag.sh BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC true
#   set_l1_runtime_flag.sh BESTCHANGE_L1_SHADOW_ENABLED false
set -euo pipefail

KEY="${1:-}"
VALUE="${2:-}"
BACKEND_ROOT="${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
SHARED_ENV="${EXSWAPING_BACKEND_SHARED_ENV:-/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/config/.env}"
REPAIR_SYMLINK="${EXSWAPING_REPAIR_ENV_SYMLINK:-0}"
BACKEND_USER="${EXSWAPING_BACKEND_USER:-app_exswapin_usr}"

case "${KEY}" in
  BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC|BESTCHANGE_L1_SHADOW_ENABLED|BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT) ;;
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
# If a prior root sed -i left the file unreadable to the app user, repair first.
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
  printf '\n# L1 runtime flag\n%s=%s\n' "${KEY}" "${VALUE}" >> "${tmp}"
fi

# Atomic replace preserving governance ownership (do not leave root:root 600).
install -o "${owner}" -g "${group}" -m "${mode}" "${tmp}" "${SHARED_ENV}"

echo "SET ${KEY}=${VALUE} shared=${SHARED_ENV} owner=${owner}:${group} mode=${mode}"
grep -E "^${KEY}=" "${SHARED_ENV}" | tail -1

if ! sudo -u "${BACKEND_USER}" test -r "${SHARED_ENV}"; then
  echo "FAIL app user ${BACKEND_USER} cannot read shared env after write" >&2
  exit 4
fi

# Verify effective value in compiler-equivalent context (unless skipped).
if [[ "${EXSWAPING_SKIP_EFFECTIVE_VERIFY:-0}" != "1" ]]; then
  PHP_BIN="${EXSWAPING_PHP_BIN:-/usr/bin/php8.4}"
  eff="$(sudo -u "${BACKEND_USER}" -H -- "${PHP_BIN}" -r "
chdir('${BACKEND_ROOT}');
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\$g = getenv('${KEY}');
if (\$g !== false) { echo filter_var(\$g, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'; exit; }
\$raw = function_exists('env') ? env('${KEY}', null) : null;
if (\$raw === null) { echo 'false'; exit; }
echo filter_var(\$raw, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
" 2>/dev/null || echo error)"
  if [[ "${eff}" != "${VALUE}" ]]; then
    echo "FAIL effective_verify key=${KEY} expected=${VALUE} got=${eff}" >&2
    exit 5
  fi
  echo "EFFECTIVE_VERIFY=${KEY}=${eff}"
fi
