#!/usr/bin/env bash
# Rotation-safe observer for same-snapshot diagnostic generation metrics.
# Survives daily Laravel log rotation by globbing iex-*.log and tracking offsets
# per inode, not a single dated filename.
#
# Usage:
#   observe_l1_same_snapshot_canary.sh --state /tmp/l1_obs/state.json --out /tmp/l1_obs/gens.jsonl
set -euo pipefail

STATE=""
OUT=""
LOG_DIR="${EXSWAPING_LARAVEL_LOG_DIR:-/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/storage/logs}"
POLL_SECONDS="${POLL_SECONDS:-15}"
MAX_SECONDS="${MAX_SECONDS:-0}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --state) STATE="${2:-}"; shift 2 ;;
    --out) OUT="${2:-}"; shift 2 ;;
    --log-dir) LOG_DIR="${2:-}"; shift 2 ;;
    --poll) POLL_SECONDS="${2:-}"; shift 2 ;;
    --max-seconds) MAX_SECONDS="${2:-}"; shift 2 ;;
    *) echo "FAIL unknown arg: $1" >&2; exit 2 ;;
  esac
done

[[ -n "${STATE}" && -n "${OUT}" ]] || {
  echo "Usage: $0 --state STATE.json --out gens.jsonl [--log-dir DIR] [--poll N] [--max-seconds N]" >&2
  exit 2
}

mkdir -p "$(dirname "${STATE}")" "$(dirname "${OUT}")"
[[ -f "${STATE}" ]] || echo '{}' > "${STATE}"
[[ -f "${OUT}" ]] || : > "${OUT}"

echo "observer ready log_dir=${LOG_DIR} state=${STATE} out=${OUT}"

python3 - "${STATE}" "${OUT}" "${LOG_DIR}" "${POLL_SECONDS}" "${MAX_SECONDS}" <<'PY'
import json, os, re, sys, time, glob

state_path, out_path, log_dir, poll_s, max_s = sys.argv[1:6]
poll_s = int(poll_s)
max_s = int(max_s)
start = time.time()

def load_state():
    try:
        with open(state_path) as f:
            return json.load(f)
    except Exception:
        return {}

def save_state(st):
    tmp = state_path + ".tmp"
    with open(tmp, "w") as f:
        json.dump(st, f, indent=2, sort_keys=True)
    os.replace(tmp, state_path)

pat = re.compile(r"bestchange_l1_generation_metrics")
enabled_re = re.compile(r'"same_snapshot_diagnostic_enabled"\s*:\s*(\d+)')
ts_re = re.compile(r"^\[(.*?)\]")

st = load_state()
# inode -> byte offset
offsets = {str(k): int(v) for k, v in (st.get("offsets") or {}).items()}

def iter_log_files():
    files = sorted(glob.glob(os.path.join(log_dir, "iex-*.log")))
    # Prefer journal-like current stream if present
    for extra in ("laravel.log", "iex.log"):
        p = os.path.join(log_dir, extra)
        if os.path.isfile(p):
            files.append(p)
    return files

seen_fingerprints = set(st.get("seen") or [])

while True:
    if max_s > 0 and (time.time() - start) >= max_s:
        break
    for path in iter_log_files():
        try:
            inode = str(os.stat(path).st_ino)
            size = os.path.getsize(path)
        except OSError:
            continue
        pos = offsets.get(inode, 0)
        # Rotation/truncation: inode reused or file shrunk
        if pos > size:
            pos = 0
        try:
            with open(path, "rb") as fh:
                fh.seek(pos)
                chunk = fh.read()
                new_pos = fh.tell()
        except OSError:
            continue
        offsets[inode] = new_pos
        if not chunk:
            continue
        for raw in chunk.splitlines():
            if b"bestchange_l1_generation_metrics" not in raw:
                continue
            if b"bc_recalc_complete" not in raw:
                continue
            line = raw.decode("utf-8", "replace")
            en = enabled_re.search(line)
            ts_m = ts_re.search(line)
            ts = ts_m.group(1) if ts_m else ""
            enabled = int(en.group(1)) if en else -1
            fp = f"{inode}:{ts}:{enabled}:{hash(line) & 0xffffffff}"
            if fp in seen_fingerprints:
                continue
            seen_fingerprints.add(fp)
            rec = {
                "ts": ts,
                "path": path,
                "inode": inode,
                "same_snapshot_diagnostic_enabled": enabled,
                "line_snip": line[line.find("bestchange_l1_generation_metrics"): line.find("bestchange_l1_generation_metrics") + 240],
            }
            with open(out_path, "a") as out:
                out.write(json.dumps(rec, sort_keys=True) + "\n")
            print(f"GEN enabled={enabled} ts={ts} path={os.path.basename(path)}", flush=True)

    st = {"offsets": offsets, "seen": list(seen_fingerprints)[-5000:]}
    save_state(st)
    time.sleep(max(1, poll_s))
PY
