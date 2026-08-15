#!/usr/bin/env bash
#
# SEO-EN-2 — Guide page validation for /en/guides/usdt-exchange
#
# Usage:
#   bash scripts/verify-en-usdt-guide.sh
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
GUIDE_URL="${BASE_URL}/en/guides/usdt-exchange"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[en-usdt-guide] $*" >&2; }
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
if [[ "$title" == *"USDT Exchange Guide"* && "$title" == *"Exswaping"* ]]; then
  pass "title present"
else
  fail "title missing or wrong: $title"
fi

desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
if [[ "$desc" == *"exchange USDT safely"* && "$desc" == *"Exswaping"* ]]; then
  pass "meta description present"
else
  fail "meta description missing or wrong"
fi

h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
if [[ "$h1c" == "1" ]] && grep -qi 'USDT Exchange Guide' <<<"$body"; then
  pass "exactly one H1"
else
  fail "expected 1 H1, found $h1c"
fi

if grep -qi 'rel="canonical" href="https://exswaping.com/en/guides/usdt-exchange"' <<<"$body"; then
  pass "canonical self-reference"
else
  fail "canonical missing or wrong"
fi

if grep -qi 'robots" content="index,follow' <<<"$body"; then
  pass "robots index,follow"
elif grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
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

if grep -qi 'FAQPage' <<<"$body" && grep -qi '<h2 id="faq">FAQ</h2>' <<<"$body"; then
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
  "/en/" \
  "/en/faq" \
  "/en/contacts" \
  "/en/pages/AMLKYC" \
  "/en/pages/instructions"; do
  if grep -q "$path" <<<"$body"; then
    pass "internal link present $path"
  else
    fail "missing internal link $path"
  fi
done

for section in \
  "What is USDT" \
  "How USDT exchange works" \
  "Popular USDT exchange directions" \
  "TRC20 vs ERC20 vs BEP20" \
  "Fees and confirmations" \
  "Security and AML/KYC"; do
  if grep -qi "$section" <<<"$body"; then
    pass "required section present: $section"
  else
    fail "missing required section: $section"
  fi
done

ru_rub="$(fetch_status "${BASE_URL}/ru/guides/obmen-usdt-na-rubli")"
if [[ "$ru_rub" == "200" ]]; then
  pass "RU cluster guide still HTTP 200"
else
  fail "RU cluster guide HTTP $ru_rub"
fi

soft404="$(fetch_status "${BASE_URL}/en/xyzrandom404test")"
if [[ "$soft404" == "404" ]]; then
  pass "soft 404 still 404"
else
  fail "soft 404 status $soft404"
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
  if grep -q '/en/guides/usdt-exchange' "$SITEMAP_PATH"; then
    fail "EN guide unexpectedly in sitemap"
  else
    pass "EN guide not in sitemap (expected)"
  fi
  if grep -q '/en/pages/usdt-exchange' "$SITEMAP_PATH"; then
    fail "EN guide CMS alias unexpectedly in sitemap"
  else
    pass "EN guide CMS alias not in sitemap (expected)"
  fi
else
  fail "sitemap file missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "EN_USDT_GUIDE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "EN_USDT_GUIDE_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "EN_USDT_GUIDE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
