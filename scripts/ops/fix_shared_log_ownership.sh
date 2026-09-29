#!/usr/bin/env bash
# Ensure exswaping shared Laravel logs are owned by the FPM/app user so
# /etc/logrotate.d/exswaping-laravel-shared (su app_exswapin_usr) can open them.
# Safe, idempotent, no deletions.
set -euo pipefail

LOGDIR="${EXSWAPING_SHARED_LOGDIR:-/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/storage/logs}"
OWNER="${EXSWAPING_APP_USER:-app_exswapin_usr}"
GROUP="${EXSWAPING_APP_GROUP:-app_exswapin_usr}"

if [[ ! -d "$LOGDIR" ]]; then
  echo "LOGDIR_MISSING=$LOGDIR"
  exit 1
fi

mapfile -t ROOT_OWNED < <(find "$LOGDIR" -maxdepth 1 \( -name 'iex-horizon.log*' -o -name 'iex-reverb.log*' \) -user root -print 2>/dev/null || true)
if [[ ${#ROOT_OWNED[@]} -eq 0 ]]; then
  echo "ROOT_OWNED_SHARED_LOGS=0"
  echo "STATUS=OK"
  exit 0
fi

echo "ROOT_OWNED_SHARED_LOGS=${#ROOT_OWNED[@]}"
for f in "${ROOT_OWNED[@]}"; do
  echo "CHOWN $f -> ${OWNER}:${GROUP}"
  chown "${OWNER}:${GROUP}" "$f"
done
echo "STATUS=FIXED"
