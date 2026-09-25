#!/usr/bin/env bash
# Fail-closed: candidate / artifact / live derived registries must exclude GRAM Wave-1 IDs
# and match the stripped SHA when compared.
#
# Usage:
#   verify_derived_registry_artifact.sh \
#     --source <worktree-or-repo> \
#     [--artifact <release-dir>] \
#     [--live <live-app-path>] \
#     [--expect-sha256 <optional>]
set -euo pipefail

SOURCE=""
ARTIFACT=""
LIVE=""
EXPECT_SHA=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source) SOURCE="$2"; shift 2 ;;
    --artifact) ARTIFACT="$2"; shift 2 ;;
    --live) LIVE="$2"; shift 2 ;;
    --expect-sha256) EXPECT_SHA="$2"; shift 2 ;;
    *)
      echo "usage: $0 --source PATH [--artifact PATH] [--live PATH] [--expect-sha256 HEX]" >&2
      exit 2
      ;;
  esac
done

[[ -n "$SOURCE" ]] || { echo "FAIL --source required" >&2; exit 2; }

python3 - "$SOURCE" "${ARTIFACT:-}" "${LIVE:-}" "${EXPECT_SHA:-}" <<'PY'
import hashlib, json, os, sys

source, artifact, live, expect = sys.argv[1:5]
gram_ids = {str(i) for i in [
    132,410,411,412,413,414,415,416,417,418,
    419,420,421,422,423,424,425,426,
]}
wave2a_ids = {"2237"}
forbidden_ids = gram_ids | wave2a_ids
rel = "resources/rates/derived-market-baseline-directions.json"
gram_manifest_rel = "resources/rates/gram-bestchange-migrated-ids.json"
wave2a_manifest_rel = "resources/rates/wave2a-bestchange-migrated-ids.json"

def load(root: str, check_ids):
    path = os.path.join(root, rel)
    if not os.path.isfile(path):
        raise SystemExit(f"FAIL missing registry: {path}")
    raw = open(path, "rb").read()
    sha = hashlib.sha256(raw).hexdigest()
    data = json.loads(raw)
    dirs = data.get("directions") or {}
    present = sorted(int(k) for k in dirs if str(k) in check_ids)
    return path, sha, len(dirs), present

def require_gram_manifest(root: str):
    path = os.path.join(root, gram_manifest_rel)
    if not os.path.isfile(path):
        raise SystemExit(f"FAIL missing GRAM exclusion manifest: {path}")
    man = json.loads(open(path).read())
    mid = sorted(int(x) for x in (man.get("direction_ids") or []))
    if mid != sorted(int(x) for x in gram_ids):
        raise SystemExit(f"FAIL GRAM manifest IDs mismatch: {mid}")

def require_wave2a_manifest(root: str):
    path = os.path.join(root, wave2a_manifest_rel)
    if not os.path.isfile(path):
        raise SystemExit(f"FAIL missing Wave2A exclusion manifest: {path}")
    man = json.loads(open(path).read())
    mid = sorted(int(x) for x in (man.get("direction_ids") or []))
    if mid != sorted(int(x) for x in wave2a_ids):
        raise SystemExit(f"FAIL Wave2A manifest IDs mismatch: {mid}")

require_gram_manifest(source)
require_wave2a_manifest(source)
sp, ss, st, spre = load(source, forbidden_ids)
print(f"SOURCE_REGISTRY_PATH={sp}")
print(f"SOURCE_REGISTRY_SHA={ss}")
print(f"SOURCE_TOTAL_CONFIG_COUNT={st}")
print(f"SOURCE_FORBIDDEN_MIGRATED_IDS_IN_DERIVED_REGISTRY={len(spre)}")
if spre:
    print(f"SOURCE_FORBIDDEN_PRESENT={spre}")
    raise SystemExit("FAIL SOURCE contains GRAM/Wave2A migrated IDs")

hashes = {"SOURCE": ss}

if artifact:
    require_gram_manifest(artifact)
    require_wave2a_manifest(artifact)
    ap, ash, at, apre = load(artifact, forbidden_ids)
    print(f"ARTIFACT_REGISTRY_PATH={ap}")
    print(f"ARTIFACT_REGISTRY_SHA={ash}")
    print(f"ARTIFACT_TOTAL_CONFIG_COUNT={at}")
    print(f"ARTIFACT_FORBIDDEN_MIGRATED_IDS_IN_DERIVED_REGISTRY={len(apre)}")
    if apre:
        print(f"ARTIFACT_FORBIDDEN_PRESENT={apre}")
        raise SystemExit("FAIL ARTIFACT contains GRAM/Wave2A migrated IDs")
    hashes["ARTIFACT"] = ash

if live:
    # Live may predate Wave2A manifest; registry strip is mandatory for all forbidden IDs.
    lp, lsh, lt, lpre = load(live, forbidden_ids)
    print(f"LIVE_REGISTRY_PATH={lp}")
    print(f"LIVE_REGISTRY_SHA={lsh}")
    print(f"LIVE_TOTAL_CONFIG_COUNT={lt}")
    print(f"LIVE_FORBIDDEN_MIGRATED_IDS_IN_DERIVED_REGISTRY={len(lpre)}")
    if lpre:
        print(f"LIVE_FORBIDDEN_PRESENT={lpre}")
        raise SystemExit("FAIL LIVE contains GRAM/Wave2A migrated IDs")
    hashes["LIVE"] = lsh

if expect:
    for label, h in hashes.items():
        if h != expect:
            raise SystemExit(f"FAIL {label} sha {h} != expect {expect}")

# When multiple roots provided, all registry SHAs must match.
uniq = set(hashes.values())
if len(uniq) > 1:
    print(f"SHA_SET={hashes}")
    raise SystemExit("FAIL registry SHA mismatch across SOURCE/ARTIFACT/LIVE")

print("OLD_REGISTRY_CANNOT_SHIP_SILENTLY=YES")
print("VERIFY_DERIVED_REGISTRY_ARTIFACT=PASS")
PY
