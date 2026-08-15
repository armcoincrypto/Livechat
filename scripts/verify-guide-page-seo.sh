#!/usr/bin/env bash
#
# SEO-9B — Guide page validation for /ru/guides/obmen-usdt-na-rubli
#
# Usage:
#   bash scripts/verify-guide-page-seo.sh
#
# Exit codes:
#   0 = PASS
#   1 = WARNING
#   2 = FAIL
#
set -euo pipefail

BASE_URL="${BASE_URL:-https://exswaping.com}"
GUIDE_URL="${BASE_URL}/ru/guides/obmen-usdt-na-rubli"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[guide-page] $*" >&2; }
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

title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
if [[ "$title" == *"Обмен USDT на рубли"* && "$title" == *"Exswaping"* ]]; then
  pass "title present"
else
  fail "title missing or wrong: $title"
fi

desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
if [[ "$desc" == *"обменять USDT на рубли"* ]]; then
  pass "meta description present"
else
  fail "meta description missing or wrong"
fi

h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
if [[ "$h1c" == "1" ]] && grep -qi 'Обмен USDT на рубли' <<<"$body"; then
  pass "exactly one H1"
else
  fail "expected 1 H1, found $h1c"
fi

if grep -qi 'rel="canonical" href="https://exswaping.com/ru/guides/obmen-usdt-na-rubli"' <<<"$body"; then
  pass "canonical self-reference"
else
  fail "canonical missing or wrong"
fi

if grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
  fail "guide page accidentally noindexed"
else
  pass "guide page indexable (no noindex)"
fi

if grep -qi 'hreflang=' <<<"$body"; then
  warn "hreflang present on RU-only guide (unexpected but non-blocking if self-only)"
else
  pass "no hreflang on guide page"
fi

ld="$(grep -oi 'application/ld+json' <<<"$body" | wc -l | tr -d ' ')"
if [[ "$ld" -ge 1 ]]; then
  pass "JSON-LD present ($ld blocks)"
else
  fail "JSON-LD missing"
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

if grep -qi 'FAQPage' <<<"$body" && grep -qi 'Сколько времени занимает обмен USDT' <<<"$body"; then
  pass "FAQPage schema with FAQ questions present"
elif grep -qi 'FAQPage' <<<"$body" && grep -oi '<h3' <<<"$body" | head -1 | grep -q .; then
  pass "FAQPage schema with visible FAQ headings"
else
  fail "FAQPage schema or FAQ content missing"
fi

json_ld="$(python3 -c "
import re, sys
html = sys.stdin.read()
blocks = re.findall(r'<script[^>]*type=\"application/ld\\+json\"[^>]*>([^<]+)</script>', html, re.I)
print('\\n'.join(blocks))
" <<<"$body")"

if echo "$json_ld" | grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"@type": "(Product|LocalBusiness|Review)"|"aggregateRating"|"reviewRating"'; then
  fail "forbidden schema types in JSON-LD"
else
  pass "no forbidden schema types in JSON-LD"
fi

for url in \
  "$BASE_URL/ru/" \
  "$BASE_URL/ru/faq" \
  "$BASE_URL/ru/contacts" \
  "$BASE_URL/ru/pages/service" \
  "$BASE_URL/ru/pages/AMLKYC" \
  "$BASE_URL/ru/exchange/USDTTRC20/SBERRUB"; do
  st="$(fetch_status "$url")"
  if [[ "$st" == "200" ]]; then
    pass "internal link target OK $url"
  else
    fail "internal link target $url status $st"
  fi
done

soft404="$(fetch_status "$BASE_URL/ru/xyzrandom404test")"
if [[ "$soft404" == "404" ]]; then
  pass "soft 404 still 404"
else
  fail "soft 404 status $soft404"
fi

invalid="$(fetch_status "$BASE_URL/ru/exchange/INVALID/PAIR")"
if [[ "$invalid" == "404" ]]; then
  pass "invalid exchange still 404"
else
  fail "invalid exchange status $invalid"
fi

en_ex="$(fetch_body "$BASE_URL/en/exchange/DASH/USDTTRC20")"
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
  if grep -q '/ru/pages/obmen-usdt-na-rubli' "$SITEMAP_PATH"; then
    fail "duplicate CMS guide URL unexpectedly in sitemap (/ru/pages/...)"
  else
    pass "deferred guide CMS URL not in sitemap (expected)"
  fi
  if grep -q '/ru/guides/obmen-usdt-na-rubli' "$SITEMAP_PATH"; then
    fail "guide page unexpectedly added to sitemap"
  else
    pass "guide page not in sitemap (expected)"
  fi
else
  fail "sitemap file missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "GUIDE_PAGE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "GUIDE_PAGE_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "GUIDE_PAGE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
