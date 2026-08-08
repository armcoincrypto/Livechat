#!/usr/bin/env bash
# Protected patch apply for financial backend overlays (interim until atomic releases).
# Copies ALL candidate additions/changes from a Git diff set, verifies hashes, backs up,
# and refuses PHP-FPM reload unless integrity + smoke PASS.
set -euo pipefail

usage() {
  cat >&2 <<'EOF'
usage: apply_backend_protected_patch.sh \
  --candidate-commit <sha> \
  --candidate-worktree <path> \
  --live-backend <path> \
  --manifest <path> \
  --lock-owner <id> \
  [--base-commit <sha>] \
  [--dry-run 0|1] \
  [--reload-fpm 0|1]
EOF
  exit 1
}

CANDIDATE_COMMIT=""; CANDIDATE_WT=""; LIVE_BACKEND=""; MANIFEST=""; LOCK_OWNER=""
BASE_COMMIT=""; DRY_RUN=0; RELOAD_FPM=0
LOCK_SCRIPT=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --candidate-commit) CANDIDATE_COMMIT="$2"; shift 2 ;;
    --candidate-worktree) CANDIDATE_WT="$2"; shift 2 ;;
    --live-backend) LIVE_BACKEND="$2"; shift 2 ;;
    --manifest) MANIFEST="$2"; shift 2 ;;
    --lock-owner) LOCK_OWNER="$2"; shift 2 ;;
    --base-commit) BASE_COMMIT="$2"; shift 2 ;;
    --dry-run) DRY_RUN="$2"; shift 2 ;;
    --reload-fpm) RELOAD_FPM="$2"; shift 2 ;;
    *) usage ;;
  esac
done

[[ -n "$CANDIDATE_COMMIT" && -n "$CANDIDATE_WT" && -n "$LIVE_BACKEND" && -n "$MANIFEST" && -n "$LOCK_OWNER" ]] || usage
LOCK_SCRIPT="$CANDIDATE_WT/scripts/deploy/exswaping_deploy_lock.sh"
VERIFY="$CANDIDATE_WT/scripts/deploy/verify_backend_candidate_integrity.sh"
[[ -x "$LOCK_SCRIPT" || -f "$LOCK_SCRIPT" ]] || { echo "LOCK_SCRIPT_MISSING" >&2; exit 40; }

export EXSWAPING_DEPLOY_OWNER="$LOCK_OWNER"
export EXSWAPING_DEPLOY_SCOPE="backend-financial"
export EXSWAPING_DEPLOY_CANDIDATE_SHA="$CANDIDATE_COMMIT"

bash "$LOCK_SCRIPT" acquire c3b-backend

BACKUP_DIR="/var/lib/server-ops/backups/backend-protected-patch-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

cleanup_fail() {
  echo "PROTECTED_PATCH_FAILED retaining lock for investigation" >&2
}
trap cleanup_fail ERR

# Resolve changed tracked files between base and candidate (includes additions/renames)
if [[ -z "$BASE_COMMIT" ]]; then
  BASE_COMMIT=$(git -C "$CANDIDATE_WT" rev-parse "${CANDIDATE_COMMIT}^")
fi
mapfile -t CHANGED < <(git -C "$CANDIDATE_WT" diff --name-only --diff-filter=ACMR "$BASE_COMMIT" "$CANDIDATE_COMMIT")
# Always include manifest-required files
mapfile -t MANIFEST_PATHS < <(python3 -c 'import json,sys; m=json.load(open(sys.argv[1]));
print("\n".join(e if isinstance(e,str) else e["path"] for e in m["files"]))' "$MANIFEST")

declare -A NEED=()
for p in "${CHANGED[@]:-}"; do NEED["$p"]=1; done
for p in "${MANIFEST_PATHS[@]:-}"; do NEED["$p"]=1; done

echo "PROTECTED_PATCH files=${#NEED[@]} backup=$BACKUP_DIR"

for path in "${!NEED[@]}"; do
  src="$CANDIDATE_WT/$path"
  dst="$LIVE_BACKEND/$path"
  if [[ ! -f "$src" ]]; then
    echo "OMISSION_OR_MISSING_CANDIDATE $path" >&2
    exit 41
  fi
  if [[ -f "$dst" ]]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$path")"
    cp -a "$dst" "$BACKUP_DIR/$path"
  fi
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "DRY_RUN would_install $path"
    continue
  fi
  mkdir -p "$(dirname "$dst")"
  install -o app_exswapin_usr -g app_exswapin_usr -m 644 "$src" "$dst"
  echo "INSTALLED $path"
done

# Integrity gate against live (must match candidate)
bash "$VERIFY" \
  --candidate-commit "$CANDIDATE_COMMIT" \
  --candidate-worktree "$CANDIDATE_WT" \
  --live-backend "$LIVE_BACKEND" \
  --manifest "$MANIFEST" \
  --report "$BACKUP_DIR/integrity-report.json" \
  --skip-smoke 0

if [[ "$RELOAD_FPM" == "1" && "$DRY_RUN" != "1" ]]; then
  systemctl reload php8.4-fpm
  echo "PHP_FPM_RELOADED"
fi

bash "$LOCK_SCRIPT" release c3b-backend
echo "PROTECTED_PATCH_OK backup=$BACKUP_DIR"
