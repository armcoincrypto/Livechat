#!/usr/bin/env bash
# C3-B single-owner deployment coordination helpers (self-testable).
set -euo pipefail

LOCK_DIR="${EXSWAPING_DEPLOY_LOCK_DIR:-/var/lock/exswaping-heavy-owners}"
OWNER="${EXSWAPING_DEPLOY_OWNER:-$(id -un)@$$}"
TTL_SECONDS="${EXSWAPING_DEPLOY_LOCK_TTL:-7200}"
SCOPE="${EXSWAPING_DEPLOY_SCOPE:-backend-financial}"

mkdir -p "$LOCK_DIR"

acquire() {
  local name="$1"
  local path="$LOCK_DIR/$name"
  if [[ -f "$path" ]]; then
    local age=$(( $(date +%s) - $(stat -c %Y "$path") ))
    local existing_owner
    existing_owner=$(awk -F= '/^owner=/{print $2; exit}' "$path" 2>/dev/null || true)
    if [[ "$age" -lt "$TTL_SECONDS" && -n "$existing_owner" && "$existing_owner" != "$OWNER" ]]; then
      echo "BLOCKED_SECOND_OWNER path=$path owner=$existing_owner age=${age}s" >&2
      return 2
    fi
  fi
  cat >"$path" <<EOF
owner=$OWNER
scope=$SCOPE
acquired_at_utc=$(date -u +%Y%m%dT%H%M%SZ)
pid=$$
ttl_seconds=$TTL_SECONDS
EOF
  echo "ACQUIRED $path owner=$OWNER"
}

release() {
  local name="$1"
  local path="$LOCK_DIR/$name"
  if [[ ! -f "$path" ]]; then
    echo "NO_LOCK $path"; return 0
  fi
  local existing_owner
  existing_owner=$(awk -F= '/^owner=/{print $2; exit}' "$path" 2>/dev/null || true)
  if [[ -n "$existing_owner" && "$existing_owner" != "$OWNER" ]]; then
    echo "RELEASE_DENIED owner=$existing_owner requester=$OWNER" >&2
    return 3
  fi
  rm -f "$path"
  echo "RELEASED $path"
}

require_ancestors() {
  local repo="$1"; shift
  local tip="$1"; shift
  for anc in "$@"; do
    if ! git -C "$repo" merge-base --is-ancestor "$anc" "$tip"; then
      echo "MISSING_ANCESTOR $anc tip=$tip" >&2
      return 4
    fi
    echo "ANCESTOR_OK $anc"
  done
}

verify_live_sha() {
  local expected="$1"
  local live="$2"
  if [[ "$expected" != "$live" ]]; then
    echo "LIVE_SHA_MISMATCH expected=$expected live=$live" >&2
    return 5
  fi
  echo "LIVE_SHA_OK $live"
}

cmd="${1:-}"
shift || true
case "$cmd" in
  acquire) acquire "${1:-c3b-backend}" ;;
  release) release "${1:-c3b-backend}" ;;
  require-ancestors) require_ancestors "$@" ;;
  verify-live-sha) verify_live_sha "$@" ;;
  self-test)
    TMP=$(mktemp -d)
    EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerA "$0" acquire t
    if EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerB "$0" acquire t 2>/tmp/exs-lock-block.txt; then
      echo 'SELFTEST_FAIL second owner acquired' >&2; exit 10
    fi
    grep -q BLOCKED_SECOND_OWNER /tmp/exs-lock-block.txt
    EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerA "$0" release t
    echo SELFTEST_OK
    ;;
  *)
    echo "usage: $0 acquire|release|require-ancestors|verify-live-sha|self-test" >&2
    exit 1
    ;;
esac
