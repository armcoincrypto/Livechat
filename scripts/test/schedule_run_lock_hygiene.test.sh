#!/usr/bin/env bash
# Proves schedule-run lock is NOT held by background children when the
# wrapper closes FD 9 for the schedule:run process.
set -euo pipefail

TMPDIR_TEST="$(mktemp -d)"
LOCK="${TMPDIR_TEST}/schedule-run.lock"
LOG="${TMPDIR_TEST}/log.jsonl"
: >"${LOCK}"

# Simulate wrapper: hold lock in parent, start long child with FD closed.
(
  exec 9>"${LOCK}"
  flock -n 9
  # "schedule:run" returns immediately after backgrounding
  bash -c 'sleep 5' 9>&- &
  echo "{\"event\":\"schedule_run_finished\"}" >>"${LOG}"
)
# After wrapper returns, lock must be free even while sleep 5 continues
if flock -n "${LOCK}" -c 'true'; then
  echo "PASS lock free after schedule_run returns while background child alive"
else
  echo "FAIL lock still held after schedule_run returned" >&2
  exit 1
fi

# Contrast: inherited FD (today's cron defect)
(
  exec 9>"${LOCK}"
  flock -n 9
  bash -c 'sleep 3' &   # inherits FD 9
  # parent exits immediately — child keeps lock
)
sleep 0.2
if flock -n "${LOCK}" -c 'true'; then
  echo "FAIL inherited-FD case unexpectedly free" >&2
  exit 1
else
  echo "PASS inherited-FD case blocks (documents current defect)"
fi
wait || true

# Wrapper script self-check if present
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WRAP="${ROOT}/scripts/ops/run_schedule_run_once.sh"
[[ -x "${WRAP}" ]] || chmod +x "${WRAP}"
# Syntax / help path only — do not invoke live artisan schedule:run here
bash -n "${WRAP}"
echo "PASS wrapper bash -n"

# BC safety strings must remain in schedule
SCHED="${ROOT}/app/Console/Schedule/ExchangeSchedule.php"
grep -q 'withoutOverlapping(20)' "${SCHED}"
grep -q 'compiler:bestchange' "${SCHED}"
grep -q 'merchant:webhook' "${SCHED}"
echo "PASS BC mutex(20) and merchant:webhook still present"

rm -rf "${TMPDIR_TEST}"
echo "ALL_SCHEDULE_RUN_LOCK_HYGIENE_TESTS_OK"
