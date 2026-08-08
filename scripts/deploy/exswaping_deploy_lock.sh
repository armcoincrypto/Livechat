#!/usr/bin/env bash
# C3-B / release-integrity single-owner deployment coordination helpers.
set -euo pipefail

LOCK_DIR="${EXSWAPING_DEPLOY_LOCK_DIR:-/var/lock/exswaping-heavy-owners}"
OWNER="${EXSWAPING_DEPLOY_OWNER:-$(id -un)@$$}"
TTL_SECONDS="${EXSWAPING_DEPLOY_LOCK_TTL:-7200}"
SCOPE="${EXSWAPING_DEPLOY_SCOPE:-backend-financial}"
CANDIDATE_SHA="${EXSWAPING_DEPLOY_CANDIDATE_SHA:-}"
HEARTBEAT_SECONDS="${EXSWAPING_DEPLOY_LOCK_HEARTBEAT:-300}"

mkdir -p "$LOCK_DIR"

write_lock() {
  local path="$1"
  cat >"$path" <<EOF
owner=$OWNER
scope=$SCOPE
candidate_sha=$CANDIDATE_SHA
acquired_at_utc=$(date -u +%Y%m%dT%H%M%SZ)
heartbeat_at_utc=$(date -u +%Y%m%dT%H%M%SZ)
pid=$$
ttl_seconds=$TTL_SECONDS
heartbeat_seconds=$HEARTBEAT_SECONDS
EOF
}

acquire() {
  local name="$1"
  local path="$LOCK_DIR/$name"
  if [[ -f "$path" ]]; then
    local age=$(( $(date +%s) - $(stat -c %Y "$path") ))
    local existing_owner existing_sha
    existing_owner=$(awk -F= '/^owner=/{print $2; exit}' "$path" 2>/dev/null || true)
    existing_sha=$(awk -F= '/^candidate_sha=/{print $2; exit}' "$path" 2>/dev/null || true)
    if [[ "$age" -lt "$TTL_SECONDS" && -n "$existing_owner" && "$existing_owner" != "$OWNER" ]]; then
      echo "BLOCKED_SECOND_OWNER path=$path owner=$existing_owner age=${age}s candidate_sha=$existing_sha" >&2
      return 2
    fi
  fi
  write_lock "$path"
  echo "ACQUIRED $path owner=$OWNER candidate_sha=$CANDIDATE_SHA"
}

heartbeat() {
  local name="$1"
  local path="$LOCK_DIR/$name"
  [[ -f "$path" ]] || { echo "NO_LOCK $path" >&2; return 3; }
  local existing_owner
  existing_owner=$(awk -F= '/^owner=/{print $2; exit}' "$path" 2>/dev/null || true)
  if [[ -n "$existing_owner" && "$existing_owner" != "$OWNER" ]]; then
    echo "HEARTBEAT_DENIED owner=$existing_owner requester=$OWNER" >&2
    return 3
  fi
  write_lock "$path"
  echo "HEARTBEAT_OK $path"
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

require_candidate_sha() {
  local expected="$1"
  local path="$LOCK_DIR/${2:-c3b-backend}"
  local locked
  locked=$(awk -F= '/^candidate_sha=/{print $2; exit}' "$path" 2>/dev/null || true)
  if [[ -z "$locked" || "$locked" != "$expected" ]]; then
    echo "CANDIDATE_SHA_LOCK_MISMATCH expected=$expected locked=$locked" >&2
    return 6
  fi
  echo "CANDIDATE_SHA_LOCK_OK $expected"
}

cmd="${1:-}"
shift || true
case "$cmd" in
  acquire) acquire "${1:-c3b-backend}" ;;
  heartbeat) heartbeat "${1:-c3b-backend}" ;;
  release) release "${1:-c3b-backend}" ;;
  require-ancestors) require_ancestors "$@" ;;
  verify-live-sha) verify_live_sha "$@" ;;
  require-candidate-sha) require_candidate_sha "$@" ;;
  self-test)
    TMP=$(mktemp -d)
    EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerA EXSWAPING_DEPLOY_CANDIDATE_SHA=abc "$0" acquire t
    if EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerB EXSWAPING_DEPLOY_CANDIDATE_SHA=def "$0" acquire t 2>/tmp/exs-lock-block.txt; then
      echo 'SELFTEST_FAIL second owner acquired' >&2; exit 10
    fi
    grep -q BLOCKED_SECOND_OWNER /tmp/exs-lock-block.txt
    EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerA EXSWAPING_DEPLOY_CANDIDATE_SHA=abc "$0" require-candidate-sha abc t
    if EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerA EXSWAPING_DEPLOY_CANDIDATE_SHA=abc "$0" require-candidate-sha zzz t 2>/tmp/exs-sha-block.txt; then
      echo 'SELFTEST_FAIL wrong candidate accepted' >&2; exit 11
    fi
    grep -q CANDIDATE_SHA_LOCK_MISMATCH /tmp/exs-sha-block.txt
    EXSWAPING_DEPLOY_LOCK_DIR="$TMP" EXSWAPING_DEPLOY_OWNER=ownerA "$0" release t
    echo SELFTEST_OK
    ;;
  *)
    echo "usage: $0 acquire|heartbeat|release|require-ancestors|verify-live-sha|require-candidate-sha|self-test" >&2
    exit 1
    ;;
esac
