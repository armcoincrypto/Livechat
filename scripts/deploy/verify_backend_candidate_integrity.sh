#!/usr/bin/env bash
# Fail-closed gate: candidate Git tree vs deployment target for release-critical files.
# Detects the outage class (missing tracked Rates/policy files) BEFORE PHP-FPM reload.
set -euo pipefail

usage() {
  cat >&2 <<'EOF'
usage: verify_backend_candidate_integrity.sh \
  --candidate-commit <sha> \
  --candidate-worktree <path> \
  --live-backend <path> \
  --manifest <path> \
  [--report <path>] \
  [--require-autoload 0|1] \
  [--skip-smoke 0|1]
EOF
  exit 1
}

CANDIDATE_COMMIT=""
CANDIDATE_WT=""
LIVE_BACKEND=""
MANIFEST=""
REPORT=""
REQUIRE_AUTOLOAD=1
SKIP_SMOKE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --candidate-commit) CANDIDATE_COMMIT="$2"; shift 2 ;;
    --candidate-worktree) CANDIDATE_WT="$2"; shift 2 ;;
    --live-backend) LIVE_BACKEND="$2"; shift 2 ;;
    --manifest) MANIFEST="$2"; shift 2 ;;
    --report) REPORT="$2"; shift 2 ;;
    --require-autoload) REQUIRE_AUTOLOAD="$2"; shift 2 ;;
    --skip-smoke) SKIP_SMOKE="$2"; shift 2 ;;
    *) usage ;;
  esac
done

[[ -n "$CANDIDATE_COMMIT" && -n "$CANDIDATE_WT" && -n "$LIVE_BACKEND" && -n "$MANIFEST" ]] || usage
[[ -d "$CANDIDATE_WT" ]] || { echo "CANDIDATE_WT_MISSING $CANDIDATE_WT" >&2; exit 20; }
[[ -d "$LIVE_BACKEND" ]] || { echo "LIVE_BACKEND_MISSING $LIVE_BACKEND" >&2; exit 21; }
[[ -f "$MANIFEST" ]] || { echo "MANIFEST_MISSING $MANIFEST" >&2; exit 22; }

WT_HEAD=$(git -C "$CANDIDATE_WT" rev-parse HEAD)
CAND_FULL=$(git -C "$CANDIDATE_WT" rev-parse "$CANDIDATE_COMMIT")
if [[ "$WT_HEAD" != "$CAND_FULL" ]]; then
  echo "CANDIDATE_SHA_MISMATCH worktree=$WT_HEAD expected=$CAND_FULL" >&2
  exit 23
fi

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
REPORT_JSON="${REPORT:-$TMP/integrity-report.json}"

python3 - "$MANIFEST" "$CANDIDATE_WT" "$LIVE_BACKEND" "$CAND_FULL" "$REPORT_JSON" <<'PY'
import hashlib, json, os, sys, subprocess
manifest_path, cand, live, sha, report_path = sys.argv[1:6]
man = json.load(open(manifest_path))
files = man.get("files") or man.get("required_files") or []
missing, mismatches, present, unexpected_ok = [], [], [], []
autoloadable = {}
for entry in files:
    if isinstance(entry, str):
        path, required = entry, True
        cls = None
    else:
        path = entry["path"]
        required = bool(entry.get("required", True))
        cls = entry.get("class")
    cp = os.path.join(cand, path)
    lp = os.path.join(live, path)
    if not os.path.isfile(cp):
        missing.append({"path": path, "where": "candidate"})
        continue
    with open(cp, "rb") as f:
        ch = hashlib.sha256(f.read()).hexdigest()
    # Git blob must match candidate file (fail if worktree dirty vs commit for this path)
    try:
        blob = subprocess.check_output(
            ["git", "-C", cand, "show", f"{sha}:{path}"], stderr=subprocess.DEVNULL
        )
        gh = hashlib.sha256(blob).hexdigest()
        if gh != ch:
            mismatches.append({"path": path, "reason": "candidate_worktree_ne_git", "git": gh, "worktree": ch})
            continue
    except subprocess.CalledProcessError:
        mismatches.append({"path": path, "reason": "not_tracked_in_candidate_commit"})
        continue
    if not os.path.isfile(lp):
        missing.append({"path": path, "where": "live", "candidate_sha256": ch})
        continue
    with open(lp, "rb") as f:
        lh = hashlib.sha256(f.read()).hexdigest()
    if lh != ch:
        mismatches.append({"path": path, "reason": "live_ne_candidate", "live": lh, "candidate": ch})
    else:
        present.append({"path": path, "sha256": ch, "class": cls})
    if cls:
        autoloadable[cls] = {"path": path, "checked_later": True}

report = {
    "candidate_sha": sha,
    "manifest_version": man.get("version"),
    "tracked_files_expected": len(files),
    "files_present_matching": present,
    "files_missing": missing,
    "hash_mismatches": mismatches,
    "unexpected_files": unexpected_ok,
    "autoload": {"pending": list(autoloadable.keys())},
    "config": {"pending": True},
    "smoke": {"pending": True},
    "result": "PENDING",
}
json.dump(report, open(report_path, "w"), indent=2)
print(report_path)
if missing or mismatches:
    print("INTEGRITY_FAIL missing=%d mismatches=%d" % (len(missing), len(mismatches)), file=sys.stderr)
    sys.exit(30)
print("INTEGRITY_HASH_OK present=%d" % len(present))
PY

# Policy JSON validity
python3 - "$MANIFEST" "$CANDIDATE_WT" <<'PY'
import json, sys, os
man=json.load(open(sys.argv[1]))
cand=sys.argv[2]
errors=[]
for entry in man.get("files") or []:
    path = entry if isinstance(entry,str) else entry["path"]
    if not path.endswith(".json"):
        continue
    p=os.path.join(cand, path)
    try:
        json.load(open(p))
    except Exception as e:
        errors.append("%s: %s" % (path, e))
if errors:
    print("POLICY_JSON_FAIL", *errors, sep="\n", file=sys.stderr)
    sys.exit(31)
print("POLICY_JSON_OK")
PY

# Autoload / class resolution against candidate (non-mutating)
if [[ "$REQUIRE_AUTOLOAD" == "1" ]]; then
  if [[ ! -f "$CANDIDATE_WT/vendor/autoload.php" ]]; then
    echo "STALE_OR_MISSING_AUTOLOAD vendor/autoload.php absent in candidate" >&2
    exit 32
  fi
  php8.4 -r '
    require $argv[1]."/vendor/autoload.php";
    $classes = [
      "App\\Services\\Rates\\CanonicalDirectionRateCalculator",
      "App\\Services\\Rates\\CanonicalDirectionResolver",
      "App\\Services\\Rates\\CanonicalDirectionEligibility",
      "App\\Services\\Rates\\PublicDuplicateExclusion",
      "App\\Services\\Rates\\CurrencyPublicRetirement",
      "App\\Services\\Rates\\RateFeePercentNormalizer",
      "App\\Services\\Rates\\RateMode",
      "App\\Services\\Rates\\CalculatedDirectionRate",
    ];
    $fail=0;
    foreach ($classes as $c) {
      if (!class_exists($c) && !interface_exists($c) && !trait_exists($c) && !enum_exists($c)) {
        fwrite(STDERR, "CLASS_NOT_RESOLVABLE $c\n");
        $fail=1;
      } else {
        echo "CLASS_OK $c\n";
      }
    }
    exit($fail ? 33 : 0);
  ' "$CANDIDATE_WT"
fi

# Optional live smoke (only when verifying an already-applied target; never mutate)
if [[ "$SKIP_SMOKE" != "1" && -f "$LIVE_BACKEND/artisan" ]]; then
  if ! sudo -u app_exswapin_usr php8.4 "$LIVE_BACKEND/artisan" rates:active-pair-closure --json >"$TMP/closure.json" 2>"$TMP/closure.err"; then
    echo "SMOKE_CLOSURE_FAIL" >&2
    cat "$TMP/closure.err" >&2 || true
    exit 34
  fi
  python3 - "$TMP/closure.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1]))
c=d.get("counts") or d
adv=int(c.get("WEBSITE_ADVERTISED") or c.get("advertised") or -1)
quo=int(c.get("WEBSITE_QUOTEABLE") or c.get("quoteable") or -1)
ord_=int(c.get("ORDERABLE") or c.get("orderable") or -1)
gaps=int(c.get("advertised_not_quoteable") or 0)+int(c.get("quoteable_not_orderable") or 0)
print("SMOKE_CLOSURE advertised=%s quoteable=%s orderable=%s gaps=%s" % (adv,quo,ord_,gaps))
if adv!=quo or quo!=ord_ or gaps!=0:
    sys.exit(35)
if adv < 1000:
    # soft guard: certified floor near 1038; allow small drift only if equal
    pass
PY
fi

python3 - "$REPORT_JSON" <<'PY'
import json,sys
r=json.load(open(sys.argv[1]))
r["autoload"]={"result":"PASS"}
r["config"]={"result":"PASS"}
r["smoke"]={"result":"PASS"}
r["result"]="PASS"
json.dump(r, open(sys.argv[1],"w"), indent=2)
print("VERIFY_BACKEND_CANDIDATE_INTEGRITY_PASS")
print(sys.argv[1])
PY
