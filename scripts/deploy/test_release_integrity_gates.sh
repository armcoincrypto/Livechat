#!/usr/bin/env bash
# Isolated negative tests for release-integrity gates (never mutates live).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
VERIFY="$ROOT/scripts/deploy/verify_backend_candidate_integrity.sh"
LOCK="$ROOT/scripts/deploy/exswaping_deploy_lock.sh"
MANIFEST="$ROOT/resources/deploy/release-integrity-manifest.json"
SHA=$(git -C "$ROOT" rev-parse HEAD)

echo "== lock self-test =="
bash "$LOCK" self-test

echo "== negative: omit required class from fake live =="
FAKE=$(mktemp -d)
# populate fake live with all manifest files then delete one
python3 - "$MANIFEST" "$ROOT" "$FAKE" <<'PY'
import json, os, shutil, sys
man, root, fake = sys.argv[1:4]
for e in json.load(open(man))["files"]:
    path = e if isinstance(e, str) else e["path"]
    src=os.path.join(root, path)
    if not os.path.isfile(src):
        continue
    dst=os.path.join(fake, path)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copy2(src, dst)
os.remove(os.path.join(fake, "app/Services/Rates/PublicDuplicateExclusion.php"))
print("removed PublicDuplicateExclusion from fake live")
PY
# candidate still complete; live missing → must fail
if bash "$VERIFY" --candidate-commit "$SHA" --candidate-worktree "$ROOT" --live-backend "$FAKE" \
  --manifest "$MANIFEST" --require-autoload 0 --skip-smoke 1 --report "$FAKE/report.json"; then
  echo "NEGTEST_FAIL missing class not rejected" >&2
  exit 50
fi
python3 -c 'import json,sys; r=json.load(open(sys.argv[1])); assert r["files_missing"]; print("NEGTEST_MISSING_CLASS_OK", r["files_missing"][0])' "$FAKE/report.json"

echo "== negative: hash mismatch =="
FAKE2=$(mktemp -d)
python3 - "$MANIFEST" "$ROOT" "$FAKE2" <<'PY'
import json, os, shutil, sys
man, root, fake = sys.argv[1:4]
for e in json.load(open(man))["files"]:
    path = e if isinstance(e, str) else e["path"]
    src=os.path.join(root, path)
    if not os.path.isfile(src):
        continue
    dst=os.path.join(fake, path)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copy2(src, dst)
p=os.path.join(fake,"resources/rates/public-duplicate-exclusions.json")
open(p,"a").write("\n")
PY
if bash "$VERIFY" --candidate-commit "$SHA" --candidate-worktree "$ROOT" --live-backend "$FAKE2" \
  --manifest "$MANIFEST" --require-autoload 0 --skip-smoke 1 --report "$FAKE2/report.json"; then
  echo "NEGTEST_FAIL hash mismatch not rejected" >&2
  exit 51
fi
python3 -c 'import json,sys; r=json.load(open(sys.argv[1])); assert r["hash_mismatches"]; print("NEGTEST_HASH_MISMATCH_OK")' "$FAKE2/report.json"

echo "== negative: wrong candidate SHA vs worktree =="
if bash "$VERIFY" --candidate-commit "0000000000000000000000000000000000000000" --candidate-worktree "$ROOT" \
  --live-backend "$FAKE2" --manifest "$MANIFEST" --require-autoload 0 --skip-smoke 1; then
  echo "NEGTEST_FAIL wrong sha accepted" >&2
  exit 52
fi
echo "NEGTEST_WRONG_SHA_OK"

echo "ALL_RELEASE_INTEGRITY_NEGTESTS_OK"
