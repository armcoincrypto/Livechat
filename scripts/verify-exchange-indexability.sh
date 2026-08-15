#!/usr/bin/env bash
#
# SEO-3 — Exchange indexability monitor (sitemap-tier crawl budget guard).
#
# Usage (from Laravel app root):
#   bash scripts/verify-exchange-indexability.sh
#
# Environment (optional):
#   BASE_URL     default https://exswaping.com
#   SITEMAP_PATH default public/static/seo/sitemap.xml
#   NON_SITEMAP_RU_PAIR default ADA/BRBKZT (valid pair, not in sitemap)
#
# Exit codes:
#   0 = PASS
#   1 = WARNING
#   2 = FAIL
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
NON_SITEMAP_RU_PAIR="${NON_SITEMAP_RU_PAIR:-ADA/BRBKZT}"
EN_PAIR="${EN_PAIR:-DASH/USDTTRC20}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[exchange-indexability] $*" >&2; }

fail() {
  log "FAIL $*"
  FAIL_COUNT=$((FAIL_COUNT + 1))
}

warn() {
  log "WARN $*"
  WARN_COUNT=$((WARN_COUNT + 1))
}

pass() {
  log "PASS $*"
}

fetch_body() {
  curl -sS --max-time 45 "$1" || true
}

fetch_status() {
  curl -sSI --max-time 30 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

has_noindex() {
  grep -qi 'name="robots"[^>]*content="[^"]*noindex' <<<"$1"
}

has_canonical() {
  grep -qi 'rel="canonical"' <<<"$1"
}

has_hreflang() {
  grep -qi 'hreflang=' <<<"$1"
}

if [[ ! -f "$SITEMAP_PATH" ]]; then
  echo "EXCHANGE_INDEXABILITY_STATUS=FAIL" >&2
  log "FAIL sitemap missing: $SITEMAP_PATH"
  exit 2
fi

SITEMAP_PAIR="$(grep -oE 'https://exswaping.com/ru/exchange/[^<]+' "$SITEMAP_PATH" | head -1 || true)"
if [[ -z "$SITEMAP_PAIR" ]]; then
  fail "no RU exchange URLs found in sitemap"
  SITEMAP_PAIR="${BASE_URL}/ru/exchange/DASH/USDTTRC20"
else
  pass "sitemap sample: $SITEMAP_PAIR"
fi

SITEMAP_BODY="$(fetch_body "$SITEMAP_PAIR" || true)"
if [[ -z "$SITEMAP_BODY" ]]; then
  fail "could not fetch sitemap exchange sample: $SITEMAP_PAIR"
else
  if has_noindex "$SITEMAP_BODY"; then
    fail "sitemap RU exchange is noindexed: $SITEMAP_PAIR"
  else
    pass "sitemap RU exchange is indexable (no noindex)"
  fi
  if ! has_canonical "$SITEMAP_BODY"; then
    fail "sitemap RU exchange missing canonical"
  else
    pass "sitemap RU exchange has canonical"
  fi
  if has_hreflang "$SITEMAP_BODY"; then
    fail "sitemap RU exchange emits hreflang"
  else
    pass "sitemap RU exchange has no hreflang"
  fi
fi

EN_URL="${BASE_URL}/en/exchange/${EN_PAIR}"
EN_BODY="$(fetch_body "$EN_URL" || true)"
if [[ -z "$EN_BODY" ]]; then
  fail "could not fetch EN exchange sample: $EN_URL"
else
  if has_noindex "$EN_BODY"; then
    pass "EN exchange is noindex,follow: $EN_URL"
  else
    fail "EN exchange missing noindex: $EN_URL"
  fi
  if ! has_canonical "$EN_BODY"; then
    fail "EN exchange missing canonical"
  else
    pass "EN exchange has canonical"
  fi
  if has_hreflang "$EN_BODY"; then
    fail "EN exchange emits hreflang"
  else
    pass "EN exchange has no hreflang"
  fi
fi

NON_SITEMAP_URL="${BASE_URL}/ru/exchange/${NON_SITEMAP_RU_PAIR}"
NON_SITEMAP_STATUS="$(fetch_status "$NON_SITEMAP_URL" || echo "000")"
if [[ "$NON_SITEMAP_STATUS" != "200" ]]; then
  warn "non-sitemap RU sample not HTTP 200 ($NON_SITEMAP_STATUS): $NON_SITEMAP_URL — skipping body checks"
else
  NON_SITEMAP_BODY="$(fetch_body "$NON_SITEMAP_URL")"
  if has_noindex "$NON_SITEMAP_BODY"; then
    pass "non-sitemap RU exchange is noindex,follow: $NON_SITEMAP_URL"
  else
    fail "non-sitemap RU exchange missing noindex: $NON_SITEMAP_URL"
  fi
  if ! has_canonical "$NON_SITEMAP_BODY"; then
    fail "non-sitemap RU exchange missing canonical"
  else
    pass "non-sitemap RU exchange has canonical"
  fi
  if has_hreflang "$NON_SITEMAP_BODY"; then
    fail "non-sitemap RU exchange emits hreflang"
  else
    pass "non-sitemap RU exchange has no hreflang"
  fi
fi

INVALID_URL="${BASE_URL}/ru/exchange/INVALID/PAIR"
INVALID_STATUS="$(fetch_status "$INVALID_URL")"
INVALID_STATUS="${INVALID_STATUS:-000}"
if [[ "$INVALID_STATUS" == "404" ]]; then
  pass "invalid exchange returns 404"
else
  fail "invalid exchange expected 404, got $INVALID_STATUS"
fi
INVALID_BODY="$(fetch_body "$INVALID_URL" 2>/dev/null || true)"
if has_canonical "$INVALID_BODY"; then
  fail "invalid exchange has canonical"
else
  pass "invalid exchange has no canonical"
fi
if has_hreflang "$INVALID_BODY"; then
  fail "invalid exchange emits hreflang"
else
  pass "invalid exchange has no hreflang"
fi

HOME_BODY="$(fetch_body "${BASE_URL}/ru/" || true)"
if [[ -z "$HOME_BODY" ]]; then
  fail "could not fetch homepage"
else
  if has_noindex "$HOME_BODY"; then
    fail "homepage accidentally noindexed"
  else
    pass "homepage is not noindexed"
  fi
  if ! has_canonical "$HOME_BODY"; then
    warn "homepage missing canonical in HTML grep"
  else
    pass "homepage has canonical"
  fi
  if ! has_hreflang "$HOME_BODY"; then
    warn "homepage missing hreflang in HTML grep"
  else
    pass "homepage has hreflang"
  fi
fi

FAQ_BODY="$(fetch_body "${BASE_URL}/ru/faq" || true)"
if [[ -z "$FAQ_BODY" ]]; then
  warn "could not fetch FAQ page"
else
  if has_noindex "$FAQ_BODY"; then
    fail "FAQ accidentally noindexed"
  else
    pass "FAQ is not noindexed"
  fi
fi

SITEMAP_EXCHANGE_COUNT="$(grep -oE 'https://exswaping.com/[^<]+/exchange/[^<]+' "$SITEMAP_PATH" | wc -l | tr -d ' ')"
log "INFO sitemap exchange URL count: $SITEMAP_EXCHANGE_COUNT"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "EXCHANGE_INDEXABILITY_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "EXCHANGE_INDEXABILITY_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "EXCHANGE_INDEXABILITY_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
