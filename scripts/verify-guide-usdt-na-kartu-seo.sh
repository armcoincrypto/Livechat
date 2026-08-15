#!/usr/bin/env bash
#
# SEO-9F — Guide page validation for /ru/guides/obmen-usdt-na-kartu
#
# Usage:
#   bash scripts/verify-guide-usdt-na-kartu-seo.sh
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
GUIDE_URL="${BASE_URL}/ru/guides/obmen-usdt-na-kartu"
RUB_GUIDE_URL="${BASE_URL}/ru/guides/obmen-usdt-na-rubli"
TRC20_GUIDE_URL="${BASE_URL}/ru/guides/obmen-usdt-trc20"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[kartu-guide] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

body="$(fetch_body "$GUIDE_URL")"
status="$(fetch_status "$GUIDE_URL")"

if [[ "$status" == "200" ]]; then
  pass "guide page HTTP 200"
else
  fail "guide page HTTP $status (expected 200)"
fi

if grep -qi '>Error<' <<<"$body" || grep -qi 'class="error' <<<"$body"; then
  fail "visible Error on guide page"
else
  pass "no visible Error"
fi

title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
if [[ "$title" == *"USDT на карту"* && "$title" == *"Exswaping"* ]]; then
  pass "title present"
else
  fail "title missing or wrong: $title"
fi

desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
if [[ "$desc" == *"карт"* && "$desc" == *"USDT"* ]]; then
  pass "meta description present"
else
  fail "meta description missing or wrong"
fi

h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
if [[ "$h1c" == "1" ]] && grep -qi 'банковскую карту' <<<"$body"; then
  pass "exactly one H1"
else
  fail "expected 1 H1, found $h1c"
fi

if grep -qi 'rel="canonical" href="https://exswaping.com/ru/guides/obmen-usdt-na-kartu"' <<<"$body"; then
  pass "canonical self-reference"
else
  fail "canonical missing or wrong"
fi

if grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
  fail "guide page accidentally noindexed"
else
  pass "guide page indexable (no noindex)"
fi

if grep -qi '"@type":"Article"' <<<"$body" || grep -qi '"@type": "Article"' <<<"$body"; then
  pass "Article schema present"
else
  fail "Article schema missing"
fi

if grep -qi 'BreadcrumbList' <<<"$body"; then
  pass "BreadcrumbList schema present"
else
  fail "BreadcrumbList schema missing"
fi

if grep -qi 'FAQPage' <<<"$body" && grep -qi 'CARDRUB' <<<"$body"; then
  pass "FAQPage schema with visible FAQ"
else
  fail "FAQPage schema or FAQ content missing"
fi

if grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"aggregateRating"|"reviewRating"' <<<"$body"; then
  fail "forbidden schema types present"
else
  pass "no forbidden schema types"
fi

for path in \
  "/ru/guides/obmen-usdt-na-rubli" \
  "/ru/guides/obmen-usdt-trc20" \
  "/ru/faq" \
  "/ru/contacts" \
  "/ru/pages/AMLKYC" \
  "/ru/pages/instructions"; do
  if grep -q "$path" <<<"$body"; then
    pass "internal link present $path"
  else
    fail "missing internal link $path"
  fi
done

for pair in CARDRUB SBERRUB TCSBRUB SBPRUB ACRUB RFBRUB; do
  path="/ru/exchange/USDTTRC20/${pair}"
  if grep -q "$path" <<<"$body"; then
    pass "Tier A link in content $path"
    ex_body="$(fetch_body "${BASE_URL}${path}")"
    ex_st="$(fetch_status "${BASE_URL}${path}")"
    if [[ "$ex_st" == "200" ]] && ! grep -qi 'robots" content="[^"]*noindex' <<<"$ex_body"; then
      pass "Tier A link valid $path"
    else
      fail "Tier A link invalid $path (status=$ex_st)"
    fi
  else
    fail "missing Tier A link $path"
  fi
done

for parent in "$RUB_GUIDE_URL" "$TRC20_GUIDE_URL"; do
  pst="$(fetch_status "$parent")"
  if [[ "$pst" == "200" ]]; then
    pass "cluster guide still HTTP 200 $parent"
  else
    fail "cluster guide HTTP $pst for $parent"
  fi
done

soft404="$(fetch_status "${BASE_URL}/ru/xyzrandom404test")"
if [[ "$soft404" == "404" ]]; then
  pass "soft 404 still 404"
else
  fail "soft 404 status $soft404"
fi

invalid="$(fetch_status "${BASE_URL}/ru/exchange/INVALID/PAIR")"
if [[ "$invalid" == "404" ]]; then
  pass "invalid exchange still 404"
else
  fail "invalid exchange status $invalid"
fi

en_ex="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
if grep -qi 'robots" content="[^"]*noindex' <<<"$en_ex"; then
  pass "EN exchange still noindex"
else
  fail "EN exchange missing noindex"
fi

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  if [[ "$count" == "198" ]]; then
    pass "sitemap count unchanged (198)"
  else
    fail "sitemap count $count (expected 198)"
  fi
  if grep -q '/ru/guides/obmen-usdt-na-kartu' "$SITEMAP_PATH"; then
    fail "kartu guide unexpectedly in sitemap"
  else
    pass "kartu guide not in sitemap (expected)"
  fi
  if grep -q '/ru/pages/obmen-usdt-na-kartu' "$SITEMAP_PATH"; then
    fail "kartu CMS alias unexpectedly in sitemap"
  else
    pass "kartu CMS alias not in sitemap (expected)"
  fi
else
  fail "sitemap file missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "KARTU_GUIDE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "KARTU_GUIDE_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "KARTU_GUIDE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
