#!/usr/bin/env bash
# Single-flight Laravel schedule:run WITHOUT leaking the lock FD into
# runInBackground children.
#
# Root cause (2026-09-27 forensics): cron
#   flock -n schedule-run.lock php artisan schedule:run
# left the lock FD open in the PHP process. Laravel's runInBackground
# shells inherited that FD, so merchant:webhook (and other long jobs)
# held schedule-run.lock for their full runtime. Subsequent cron minutes
# skipped the entire scheduler (BC, rates health, etc.).
#
# Wave3 intent (crontab comment): lock should be short-lived once heavy
# jobs runInBackground. This wrapper restores that intent:
#   - parent shell holds flock until schedule:run RETURNS
#   - PHP is started with the lock FD closed (9>&-) so background
#     children cannot inherit it
#   - per-job withoutOverlapping / onOneServer / compiler flock unchanged
#
# Crontab (app_exswapin_usr), replace the bare flock|php line with:
#   * * * * * /opt/exswaping-owned-ops/current/scripts/ops/run_schedule_run_once.sh >/dev/null
# or, until ops symlink updated, point at the live release copy:
#   * * * * * /var/www/app_exswapin_usr/data/www/app.exswaping.com/scripts/ops/run_schedule_run_once.sh >/dev/null
#
# Does NOT mutate rates, ownership, or merchant payment outcomes.
set -euo pipefail

PHP_BIN="${EXSWAPING_PHP_BIN:-/usr/bin/php8.4}"
BACKEND_ROOT="${EXSWAPING_BACKEND_ROOT:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
LOCK_PATH="${EXSWAPING_SCHEDULE_RUN_LOCK:-/var/lock/exswaping-artisan-schedule-run.lock}"
LOG_PATH="${EXSWAPING_SCHEDULE_RUN_LOG:-/var/lib/exswaping-owned/pricing/scheduler/schedule_run_once.jsonl}"

ARTISAN="${BACKEND_ROOT}/artisan"
if [[ ! -f "${ARTISAN}" ]]; then
  echo "FAIL missing artisan at ${ARTISAN}" >&2
  exit 2
fi

mkdir -p "$(dirname "${LOCK_PATH}")" "$(dirname "${LOG_PATH}")"
[[ -e "${LOCK_PATH}" ]] || (umask 011; : >"${LOCK_PATH}")

exec 9>"${LOCK_PATH}"
if ! flock -n 9; then
  ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf '%s\n' "{\"ts\":\"${ts}\",\"event\":\"schedule_run_skipped\",\"reason\":\"SCHEDULE_RUN_LOCK_HELD\",\"lock\":\"${LOCK_PATH}\"}" >>"${LOG_PATH}" || true
  exit 0
fi

start_ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
start_epoch="$(date +%s)"
# Critical: close FD 9 in the PHP child so runInBackground workers do not inherit the lock.
set +e
"${PHP_BIN}" "${ARTISAN}" schedule:run 9>&-
rc=$?
set -e
end_ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
end_epoch="$(date +%s)"
runtime=$((end_epoch - start_epoch))

printf '%s\n' "{\"ts\":\"${end_ts}\",\"event\":\"schedule_run_finished\",\"start\":\"${start_ts}\",\"runtime_seconds\":${runtime},\"exit\":${rc},\"lock_scope\":\"until_schedule_run_returns\",\"fd_inherited_by_background\":\"NO\"}" >>"${LOG_PATH}" || true

# Lock released when this shell exits (FD 9 closed).
exit "${rc}"
