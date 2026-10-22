#!/usr/bin/env bash
# Kill-switch: disable scheduled scheme:files skip. Does NOT delete skip meta.
# Requires EXS_OWNER_APPROVED=1 and explicit invocation. Does not deploy code.
set -euo pipefail
if [[ "${EXS_OWNER_APPROVED:-}" != "1" ]]; then
  echo "REFUSE: set EXS_OWNER_APPROVED=1 after owner authorization" >&2
  exit 4
fi
ENV_FILE="${EXSWAPING_BACKEND_SHARED_ENV:-/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/config/.env}"
KEY=SCHEME_FILES_SCHEDULED_SKIP_UNCHANGED
if grep -q "^${KEY}=" "$ENV_FILE"; then
  sed -i "s/^${KEY}=.*/${KEY}=false/" "$ENV_FILE"
else
  printf '\n%s=false\n' "$KEY" >> "$ENV_FILE"
fi
grep -n "^${KEY}=" "$ENV_FILE"
echo "KILL_SWITCH_APPLIED ${KEY}=false (meta key scheme_files.scheduled_skip.meta.v1 preserved)"
