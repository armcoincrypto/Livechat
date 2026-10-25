#!/usr/bin/env bash
# Align competitors-only away from BC admission minutes (:00/:30 ± boundary).
# Safe: comments the legacy 1-59/2 line and installs 3-57/2 for app_exswapin_usr.
# Does NOT touch BestChange rates, settings 252/337, or scheme-skip.
set -euo pipefail
USER_NAME="${EXSWAPING_BACKEND_USER:-app_exswapin_usr}"
NEW_LINE='3-57/2 * * * * /opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh compiler:competitors-only --link=Best >> /var/www/app_exswapin_usr/data/www/app.exswaping.com/storage/logs/compiler_competitors.log 2>&1'
MARKER='# EXSWAPING_BC_ADMISSION_COMPETITORS_OFFSET'
tmp="$(mktemp)"
crontab -u "${USER_NAME}" -l 2>/dev/null | sed '/compiler:competitors-only/s/^/# RETIRED_BC_ADMISSION /' >"${tmp}" || true
if ! grep -q '3-57/2 \* \* \* \*.*compiler:competitors-only' "${tmp}"; then
  printf '%s\n%s\n' "${MARKER}" "${NEW_LINE}" >>"${tmp}"
fi
crontab -u "${USER_NAME}" "${tmp}"
rm -f "${tmp}"
echo "COMPETITORS_CRON=3-57/2 applied for ${USER_NAME}"
crontab -u "${USER_NAME}" -l | grep -E 'competitors-only|BC_ADMISSION' || true
