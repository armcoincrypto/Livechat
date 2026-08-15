#!/usr/bin/env bash
#
# SEO-8 — Full SEO recovery validation suite (single entry point).
#
# Usage (from Laravel app root):
#   bash scripts/verify-seo-recovery-suite.sh
#
# Runs all production SEO guardrail scripts and summarizes overall status.
#
# Exit codes:
#   0 = PASS
#   1 = WARNING (only when verify-web-performance.sh warns on TTFB)
#   2 = FAIL (any SEO guardrail script fails)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

FAIL_COUNT=0
WARN_COUNT=0
WEB_PERF_EXIT=0

log() { echo "[recovery-suite] $*" >&2; }

run_check() {
  local name="$1"
  shift
  local exit_code=0
  log "RUN $name"
  "$@" || exit_code=$?
  case "$exit_code" in
    0) log "OK $name (exit 0)" ;;
    1)
      if [[ "$name" == "verify-web-performance.sh" ]]; then
        WARN_COUNT=$((WARN_COUNT + 1))
        WEB_PERF_EXIT=1
        log "WARN $name (exit 1 — TTFB warning allowed)"
      else
        FAIL_COUNT=$((FAIL_COUNT + 1))
        log "FAIL $name (exit 1)"
      fi
      ;;
    *)
      FAIL_COUNT=$((FAIL_COUNT + 1))
      log "FAIL $name (exit $exit_code)"
      ;;
  esac
  return 0
}

run_check "verify-sitemap.sh" bash scripts/verify-sitemap.sh --no-generate
run_check "verify-exchange-indexability.sh" bash scripts/verify-exchange-indexability.sh
run_check "verify-exchange-tier-policy.sh" bash scripts/verify-exchange-tier-policy.sh
run_check "verify-seo-metadata.sh" bash scripts/verify-seo-metadata.sh
run_check "verify-structured-data.sh" bash scripts/verify-structured-data.sh
run_check "verify-web-performance.sh" bash scripts/verify-web-performance.sh

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "SEO_RECOVERY_SUITE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 && "$WEB_PERF_EXIT" -eq 1 ]]; then
  echo "SEO_RECOVERY_SUITE_STATUS=WARNING" >&2
  log "RESULT WARNING (TTFB only; warnings=$WARN_COUNT)"
  exit 1
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "SEO_RECOVERY_SUITE_STATUS=FAIL" >&2
  log "RESULT FAIL (unexpected warnings=$WARN_COUNT)"
  exit 2
fi

echo "SEO_RECOVERY_SUITE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
