#!/usr/bin/env bash
#
# SEO-7 — Core Web Vitals / SSR performance validation.
#
# Usage (from Laravel app root):
#   bash scripts/verify-web-performance.sh
#
# Environment (optional):
#   BASE_URL              default https://exswaping.com
#   TIER_A_EXCHANGE_PATH  default /ru/exchange/DASH/USDTTRC20
#   EN_EXCHANGE_PATH      default /en/exchange/DASH/USDTTRC20
#   SOFT_404_PATH         default /ru/xyzrandom404test
#
# Exit codes:
#   0 = PASS
#   1 = WARNING
#   2 = FAIL
#
set -euo pipefail

BASE_URL="${BASE_URL:-https://exswaping.com}"
TIER_A_EXCHANGE_PATH="${TIER_A_EXCHANGE_PATH:-/ru/exchange/DASH/USDTTRC20}"
EN_EXCHANGE_PATH="${EN_EXCHANGE_PATH:-/en/exchange/DASH/USDTTRC20}"
SOFT_404_PATH="${SOFT_404_PATH:-/ru/xyzrandom404test}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[web-performance] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 30 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 45 "$1" || true
}

measure_ttfb() {
  curl -o /dev/null -sS --max-time 45 -w '%{time_starttransfer}' "$1" 2>/dev/null || echo "999"
}

classify_ttfb() {
  local label="$1" seconds="$2"
  awk -v s="$seconds" -v label="$label" '
    BEGIN {
      if (s < 0.8) { print "PASS " label " TTFB " sprintf("%.3fs", s) " (<0.8s OK)"; exit 0 }
      if (s <= 1.5) { print "WARN " label " TTFB " sprintf("%.3fs", s) " (0.8–1.5s)"; exit 1 }
      print "WARN " label " TTFB " sprintf("%.3fs", s) " (>1.5s, SSR-bound; network-variable)";
      exit 1
    }'
}

check_ttfb() {
  local label="$1" url="$2"
  local ttfb result
  ttfb="$(measure_ttfb "$url")"
  result="$(classify_ttfb "$label" "$ttfb" || true)"
  case "$result" in
    PASS*) pass "${result#PASS }" ;;
    WARN*) warn "${result#WARN }" ;;
    *) warn "$label TTFB measurement failed ($ttfb)" ;;
  esac
}

detect_main_js() {
  local body="$1"
  grep -oE 'main-[A-Z0-9]+\.js' <<<"$body" | head -1 || true
}

has_gzip() {
  curl -sSI --max-time 30 -H 'Accept-Encoding: gzip' "$1" 2>/dev/null | grep -qi 'content-encoding: gzip'
}

has_immutable_cache() {
  curl -sSI --max-time 30 "$1" 2>/dev/null | grep -qi 'cache-control:.*immutable'
}

has_noindex() {
  grep -qi 'name="robots"[^>]*content="[^"]*noindex' <<<"$1"
}

has_title_h1_schema() {
  local body="$1"
  local title h1c schema
  title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i' || true)"
  h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
  schema="$(grep -oi '<script type="application/ld+json"' <<<"$body" | wc -l | tr -d ' ')"

  if [[ -z "$title" ]]; then
    fail "homepage missing <title>"
    return
  fi
  pass "homepage has title"

  if [[ "$h1c" != "1" ]]; then
    fail "homepage expected 1 H1, found $h1c"
  else
    pass "homepage has single H1"
  fi

  if [[ "$schema" -lt 1 ]]; then
    fail "homepage missing JSON-LD schema"
  else
    pass "homepage has JSON-LD schema"
  fi
}

RU_HOME="${BASE_URL}/ru/"
EN_HOME="${BASE_URL}/en/"
TIER_A_URL="${BASE_URL}${TIER_A_EXCHANGE_PATH}"
EN_EX_URL="${BASE_URL}${EN_EXCHANGE_PATH}"
SOFT_404_URL="${BASE_URL}${SOFT_404_PATH}"

log "INFO measuring $RU_HOME"
RU_STATUS="$(fetch_status "$RU_HOME")"
if [[ "$RU_STATUS" == "200" ]]; then
  pass "RU homepage status 200"
else
  fail "RU homepage status $RU_STATUS (expected 200)"
fi

check_ttfb "RU homepage" "$RU_HOME"
check_ttfb "EN homepage" "$EN_HOME"
check_ttfb "Tier A exchange" "$TIER_A_URL"

if has_gzip "$RU_HOME"; then
  pass "gzip enabled on RU homepage"
else
  fail "gzip missing on RU homepage"
fi

RU_BODY="$(fetch_body "$RU_HOME")"
MAIN_JS="$(detect_main_js "$RU_BODY")"
if [[ -z "$MAIN_JS" ]]; then
  warn "could not detect main-*.js from homepage HTML"
else
  ASSET_URL="${BASE_URL}/ru/${MAIN_JS}"
  if has_immutable_cache "$ASSET_URL"; then
    pass "immutable cache on $MAIN_JS"
  else
    fail "immutable cache missing on $MAIN_JS"
  fi
fi

SOFT_STATUS="$(fetch_status "$SOFT_404_URL")"
if [[ "$SOFT_STATUS" == "404" ]]; then
  pass "soft-404 path returns 404"
else
  fail "soft-404 path status $SOFT_STATUS (expected 404)"
fi

SOFT_BODY="$(fetch_body "$SOFT_404_URL")"
if grep -qi 'application/ld+json' <<<"$SOFT_BODY"; then
  fail "404 page still emits JSON-LD"
else
  pass "404 page has no JSON-LD"
fi

if [[ -n "$RU_BODY" ]]; then
  has_title_h1_schema "$RU_BODY"
else
  fail "could not fetch RU homepage body"
fi

TIER_BODY="$(fetch_body "$TIER_A_URL")"
if [[ -z "$TIER_BODY" ]]; then
  fail "could not fetch Tier A exchange page"
elif has_noindex "$TIER_BODY"; then
  fail "Tier A exchange accidentally noindexed"
else
  pass "Tier A exchange is indexable (no noindex)"
fi

EN_BODY="$(fetch_body "$EN_EX_URL")"
if [[ -z "$EN_BODY" ]]; then
  fail "could not fetch EN exchange page"
elif has_noindex "$EN_BODY"; then
  pass "EN exchange is noindex"
else
  fail "EN exchange missing noindex"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "WEB_PERFORMANCE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "WEB_PERFORMANCE_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "WEB_PERFORMANCE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
