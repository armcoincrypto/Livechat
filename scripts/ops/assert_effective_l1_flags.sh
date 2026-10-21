#!/usr/bin/env bash
# Read-only: report L1 flags from the SAME user/cwd/path the real compiler uses.
# Exit 0 only when gate expectations match (defaults: all false for safe idle).
#
# Usage:
#   assert_effective_l1_flags.sh
#   REQUIRE_SAME_SNAPSHOT=1 REQUIRE_SHADOW=0 REQUIRE_SKIP=0 assert_effective_l1_flags.sh
set -euo pipefail

BACKEND_ROOT="${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
SHARED_ENV="${EXSWAPING_BACKEND_SHARED_ENV:-/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/config/.env}"
PHP_BIN="${EXSWAPING_PHP_BIN:-/usr/bin/php8.4}"
BACKEND_USER="${EXSWAPING_BACKEND_USER:-app_exswapin_usr}"

REQUIRE_SAME_SNAPSHOT="${REQUIRE_SAME_SNAPSHOT:-0}"
REQUIRE_SHADOW="${REQUIRE_SHADOW:-0}"
REQUIRE_SKIP="${REQUIRE_SKIP:-0}"
REQUIRE_ENV_SYMLINK="${REQUIRE_ENV_SYMLINK:-1}"

[[ -d "${BACKEND_ROOT}" && -f "${BACKEND_ROOT}/artisan" ]] || {
  echo "FAIL backend root missing: ${BACKEND_ROOT}" >&2
  exit 2
}

backend_sha="unknown"
if [[ -f "${BACKEND_ROOT}/RELEASE.json" ]]; then
  backend_sha="$(python3 -c "import json; print(json.load(open('${BACKEND_ROOT}/RELEASE.json')).get('git_sha','unknown'))" 2>/dev/null || echo unknown)"
fi

env_path="${BACKEND_ROOT}/.env"
if [[ -L "${env_path}" ]]; then
  env_type="symlink"
  env_target="$(readlink -f "${env_path}" 2>/dev/null || readlink "${env_path}")"
else
  env_type="file"
  env_target="$(readlink -f "${env_path}" 2>/dev/null || echo "${env_path}")"
fi

shared_same="$(grep -E '^BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=' "${SHARED_ENV}" 2>/dev/null | tail -1 | cut -d= -f2- || echo missing)"
shared_shadow="$(grep -E '^BESTCHANGE_L1_SHADOW_ENABLED=' "${SHARED_ENV}" 2>/dev/null | tail -1 | cut -d= -f2- || echo missing)"
shared_skip="$(grep -E '^BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT=' "${SHARED_ENV}" 2>/dev/null | tail -1 | cut -d= -f2- || echo missing)"

live_same="$(grep -E '^BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=' "${env_path}" 2>/dev/null | tail -1 | cut -d= -f2- || echo missing)"

# Effective values via the compiler user + bootstrap (Dotenv path).
read_effective() {
  local key="$1"
  sudo -u "${BACKEND_USER}" -H -- "${PHP_BIN}" -r "
chdir('${BACKEND_ROOT}');
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\$k = '${key}';
\$raw = function_exists('env') ? env(\$k, null) : null;
if (\$raw === null) {
  \$g = getenv(\$k);
  \$raw = (\$g === false) ? null : \$g;
}
if (\$raw === null) { echo 'false'; exit; }
echo filter_var(\$raw, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
" 2>/dev/null
}

eff_same="$(read_effective BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC || echo error)"
eff_shadow="$(read_effective BESTCHANGE_L1_SHADOW_ENABLED || echo error)"
eff_skip="$(read_effective BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT || echo error)"

echo "backend_sha=${backend_sha}"
echo "backend_root=${BACKEND_ROOT}"
echo "env_type=${env_type}"
echo "env_target=${env_target}"
echo "shared_env=${SHARED_ENV}"
echo "shared_same_snapshot=${shared_same}"
echo "shared_full_shadow=${shared_shadow}"
echo "shared_skip_identical=${shared_skip}"
echo "live_file_same_snapshot=${live_same}"
echo "same_snapshot_diagnostic=${eff_same}"
echo "full_shadow=${eff_shadow}"
echo "skip_identical=${eff_skip}"

fail=0
want_bool() {
  local name="$1" got="$2" want="$3"
  local want_s
  if [[ "${want}" == "1" ]]; then want_s=true; else want_s=false; fi
  if [[ "${got}" != "${want_s}" ]]; then
    echo "FAIL ${name} effective=${got} required=${want_s}" >&2
    fail=1
  fi
}

want_bool same_snapshot_diagnostic "${eff_same}" "${REQUIRE_SAME_SNAPSHOT}"
want_bool full_shadow "${eff_shadow}" "${REQUIRE_SHADOW}"
want_bool skip_identical "${eff_skip}" "${REQUIRE_SKIP}"

if [[ "${REQUIRE_ENV_SYMLINK}" == "1" ]]; then
  if [[ "${env_type}" != "symlink" ]]; then
    echo "FAIL env_type=${env_type} required=symlink (release-local .env divergence risk)" >&2
    fail=1
  fi
  case "${env_target}" in
    *exswaping-backend-shared/config/.env) ;;
    *)
      echo "FAIL env_target not shared config: ${env_target}" >&2
      fail=1
      ;;
  esac
fi

if [[ "${shared_same}" != "${live_same}" && "${env_type}" != "symlink" ]]; then
  echo "FAIL shared_vs_live_same_snapshot diverged shared=${shared_same} live=${live_same}" >&2
  fail=1
fi

if (( fail != 0 )); then
  echo "ASSERT_EFFECTIVE_L1_FLAGS=FAIL"
  exit 1
fi
echo "ASSERT_EFFECTIVE_L1_FLAGS=PASS"
exit 0
