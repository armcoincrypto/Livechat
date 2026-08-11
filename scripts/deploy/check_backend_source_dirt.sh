#!/usr/bin/env bash
# Fail-closed dirty-source gate for Exswaping backend financial/catalog deploys.
# Distinguishes production source dirt vs allowed runtime / unrelated dirt.
#
# Outputs one of:
#   SOURCE_CLEAN
#   SOURCE_DIRTY_ALLOWED_RUNTIME
#   SOURCE_DIRTY_BLOCK
#
# Exit codes:
#   0  SOURCE_CLEAN or SOURCE_DIRTY_ALLOWED_RUNTIME
#  12  SOURCE_DIRTY_BLOCK (production-critical source dirt)
#  13  SOURCE_DIRTY_BLOCK (unrelated dirt when EXSWAPING_ALLOW_UNRELATED_DIRT=0)
set -euo pipefail

ROOT="${1:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fi
cd "$ROOT"

MANIFEST="${EXSWAPING_RELEASE_MANIFEST:-$ROOT/resources/deploy/release-integrity-manifest.json}"
REPORT="${EXSWAPING_DIRT_REPORT:-}"
ALLOW_UNRELATED="${EXSWAPING_ALLOW_UNRELATED_DIRT:-1}"

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
git status --porcelain >"$TMP" 2>/dev/null || true

if [[ ! -s "$TMP" ]]; then
  echo "SOURCE_CLEAN"
  exit 0
fi

python3 - "$MANIFEST" "$TMP" "$REPORT" "$ALLOW_UNRELATED" <<'PY'
import json, os, re, sys

manifest_path, status_path, report_path, allow_unrelated = sys.argv[1:5]
prod = set()
if os.path.isfile(manifest_path):
    man = json.load(open(manifest_path))
    for e in man.get("files") or []:
        prod.add(e if isinstance(e, str) else e["path"])

PROD_RE = re.compile(
    r"^(app/Services/Rates/|app/Observers/CommercialAdjustment|"
    r"app/Providers/RatesOwnerControlServiceProvider\.php|"
    r"app/Console/Commands/RatesApplyUsdtRubCommercialTargetCommand\.php|"
    r"bootstrap/providers\.php|"
    r"resources/rates/|resources/deploy/|"
    r"config/calculator\.php|"
    r"packages/Calculator/(SourceChecks\.php|Strategies/(Derived|Zelle)BaselineStrategy\.php)|"
    r"packages/Courses/Console/UpdateCoursesConsole\.php|"
    r"scripts/deploy/(apply_backend_protected_patch|verify_backend_candidate|"
    r"exswaping_deploy_lock|test_release_integrity|check_backend_source_dirt)|"
    r"packages/BestChange/Services/DirectionExchangeRecalculateService\.php|"
    r"packages/Courses/Export/|"
    r"tests/Unit/Rates/(Zelle|CurrencyPublicRetirement|ActivePairClosure|UsdtRubCommercialTarget|UniversalAdminProfit))"
)

ALLOWED_RE = re.compile(
    r"^(\.env($|\.)|\.DS_Store$|\.phpunit\.result\.cache$|"
    r"storage/|bootstrap/cache/|vendor/|node_modules/|"
    r"public/static/exports/|docs/audits/|resources/deploy/.*\.sha256$|"
    r".*\.log$|.*\.bak($|-|\.)|.*\.pyc$|xml-changer/__pycache__/)"
)

source_dirty, runtime_allowed, unrelated = [], [], []
for line in open(status_path).read().splitlines():
    if not line or len(line) < 4:
        continue
    path = line[3:].strip().strip('"')
    if path in prod or PROD_RE.search(path):
        source_dirty.append(path)
    elif ALLOWED_RE.search(path):
        runtime_allowed.append(path)
    else:
        unrelated.append(path)

report = {
    "source_dirty": source_dirty,
    "runtime_allowed": runtime_allowed,
    "unrelated_active_work": unrelated,
}
if report_path:
    open(report_path, "w").write(json.dumps(report, indent=2) + "\n")

if source_dirty:
    print("SOURCE_DIRTY_BLOCK count=%d" % len(source_dirty))
    for p in source_dirty[:40]:
        print("  SOURCE", p)
    sys.exit(12)

if unrelated and allow_unrelated != "1":
    print("SOURCE_DIRTY_BLOCK unrelated_active_work=%d" % len(unrelated))
    sys.exit(13)

if runtime_allowed or unrelated:
    print(
        "SOURCE_DIRTY_ALLOWED_RUNTIME runtime=%d unrelated=%d"
        % (len(runtime_allowed), len(unrelated))
    )
    sys.exit(0)

print("SOURCE_CLEAN")
PY
