#!/usr/bin/env bash
#
# SEO-EN-3 — Guide page validation for /en/guides/usdt-trc20-exchange
#
# Usage:
#   bash scripts/verify-en-usdt-trc20-guide.sh
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
GUIDE_URL="${BASE_URL}/en/guides/usdt-trc20-exchange"
PARENT_URL="${BASE_URL}/en/guides/usdt-exchange"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[en-trc20-guide] $*" >&2; }
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
if [[ "$title" == *"USDT TRC20 Exchange Guide"* && "$title" == *"Exswaping"* ]]; then
  pass "title present"
else
  fail "title missing or wrong: $title"
fi

desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
if [[ "$desc" == *"USDT TRC20"* && "$desc" == *"TRON"* ]]; then
  pass "meta description present"
else
  fail "meta description missing or wrong"
fi

h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
if [[ "$h1c" == "1" ]] && grep -qi 'USDT TRC20 Exchange Guide' <<<"$body"; then
  pass "exactly one H1"
else
  fail "expected 1 H1, found $h1c"
fi

if grep -qi 'rel="canonical" href="https://exswaping.com/en/guides/usdt-trc20-exchange"' <<<"$body"; then
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

if grep -qi '<h2' <<<"$body"; then
  pass "SSR body rendered (h2 present)"
else
  fail "SSR body missing (no h2)"
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

if python3 -c "
import sys,re,json
html=sys.stdin.read()
m=re.search(r'<script type=\"application/ld\\+json\"[^>]*>(.*?)</script>', html, re.S)
if not m: sys.exit(1)
json.loads(m.group(1))
" <<<"$body" 2>/dev/null; then
  pass "JSON-LD parses valid"
else
  fail "JSON-LD invalid or missing"
fi

for path in \
  "/en/guides/usdt-exchange" \
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

for pair in BTC ETH LTC ETC KSPBKZT; do
  path="/en/exchange/USDTTRC20/${pair}"
  if grep -q "$path" <<<"$body"; then
    pass "Tier A link in content $path"
    ex_st="$(fetch_status "${BASE_URL}${path}")"
    if [[ "$ex_st" == "200" ]]; then
      pass "Tier A link reachable $path"
    else
      fail "Tier A link HTTP $ex_st for $path"
    fi
  else
    fail "missing Tier A link $path"
  fi
done

for section in \
  "What is USDT TRC20" \
  "Why TRON is popular" \
  "How USDT TRC20 exchange works" \
  "Common exchange directions" \
  "Wallet compatibility" \
  "Fees and confirmations" \
  "Security recommendations" \
  "Common mistakes"; do
  if grep -qi "$section" <<<"$body"; then
    pass "required section present: $section"
  else
    fail "missing required section: $section"
  fi
done

parent_st="$(fetch_status "$PARENT_URL")"
if [[ "$parent_st" == "200" ]]; then
  pass "parent EN guide still HTTP 200"
else
  fail "parent EN guide HTTP $parent_st"
fi

parent_body="$(fetch_body "$PARENT_URL")"
if grep -qi 'robots" content="index,follow' <<<"$parent_body" || ! grep -qi 'robots" content="[^"]*noindex' <<<"$parent_body"; then
  pass "parent EN guide still indexable"
else
  fail "parent EN guide accidentally noindexed"
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
  if grep -q '/en/guides/usdt-trc20-exchange' "$SITEMAP_PATH"; then
    fail "TRC20 guide unexpectedly in sitemap"
  else
    pass "TRC20 guide not in sitemap (expected)"
  fi
else
  fail "sitemap file missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "EN_TRC20_GUIDE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "EN_TRC20_GUIDE_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "EN_TRC20_GUIDE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
