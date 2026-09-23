#!/usr/bin/env bash
# Report status of rate/XML/release locks. Never deletes live locks.
set -euo pipefail

report_lock() {
  local path="$1" label="$2"
  if [[ ! -e "$path" ]]; then
    printf 'LOCK %-32s path=%s state=ABSENT\n' "$label" "$path"
    return 0
  fi
  local holder_pids=""
  holder_pids="$(fuser "$path" 2>/dev/null | tr -s ' ' | sed 's/^ //' || true)"
  local pyout state age_sec
  pyout="$(python3 - "$path" <<'PY'
import fcntl, os, sys, time
p = sys.argv[1]
st = os.stat(p)
age = int(time.time() - st.st_mtime)
fd = os.open(p, os.O_RDWR)
try:
    fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    print(f"FREE {age}")
    fcntl.flock(fd, fcntl.LOCK_UN)
except BlockingIOError:
    print(f"HELD {age}")
except Exception:
    print(f"ERR {age}")
os.close(fd)
PY
)"
  state="$(awk '{print $1}' <<<"$pyout")"
  age_sec="$(awk '{print $2}' <<<"$pyout")"
  local alive=N/A
  if [[ -n "${holder_pids}" ]]; then
    alive=YES
    for pid in $holder_pids; do
      [[ -d "/proc/$pid" ]] || alive=NO
    done
  elif [[ "$state" == "HELD" ]]; then
    alive=UNKNOWN
  fi
  printf 'LOCK %-32s path=%s state=%s age_sec=%s holder_pids=%s holder_alive=%s\n' \
    "$label" "$path" "$state" "$age_sec" "${holder_pids:-none}" "$alive"
  if [[ "$state" == "HELD" && "$alive" == "NO" ]]; then
    echo "STALE_LOCK_CANDIDATE label=$label path=$path (holder dead — manual review only; do not auto-delete)"
  fi
}

echo "RATE_SYSTEM_LOCK_STATUS $(date -u +%Y-%m-%dT%H:%M:%SZ)"
report_lock /var/lock/exswaping-owned-deploy.lock release_deploy
report_lock /var/lock/exswaping-owned-release-owner.lock release_owner
report_lock /var/lock/exswaping-production-mutate.lock production_mutate
report_lock /var/lock/exswaping-xml-publish.lock xml_publish
report_lock /var/lock/exswaping-xml-changer.lock xml_changer
report_lock /var/lock/exswaping-laravel-schedule.lock laravel_schedule
report_lock /var/lock/exswaping-artisan-schedule-run.lock artisan_schedule_run
report_lock /var/lock/exswaping-heavy.lock heavy
report_lock /var/lock/exswaping-heavy-job.lock heavy_job

if [[ -d /var/lock/exswaping-compiler-guards ]]; then
  for f in /var/lock/exswaping-compiler-guards/last-success.*; do
    [[ -e "$f" ]] || continue
    age=$(( $(date +%s) - $(stat -c %Y "$f") ))
    printf 'COMPILER_SUCCESS %-40s age_sec=%s content=%s\n' "$(basename "$f")" "$age" "$(tr '\n' ' ' <"$f")"
  done
fi

if [[ -e /var/lib/exswaping/rates-xml-hidden.flag ]]; then
  echo "XML_HIDE_FLAG=PRESENT path=/var/lib/exswaping/rates-xml-hidden.flag"
else
  echo "XML_HIDE_FLAG=ABSENT"
fi
