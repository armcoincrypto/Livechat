#!/usr/bin/env bash
#
# SEO-EN-4 — Guide page validation for /en/guides/usdt-to-bank-card
#
# Usage:
#   bash scripts/verify-en-usdt-to-bank-card-guide.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
GUIDE_URL="${BASE_URL}/en/guides/usdt-to-bank-card"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[en-bank-card-guide] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

body="$(fetch_body "$GUIDE_URL")"
status="$(fetch_status "$GUIDE_URL")"

[[ "$status" == "200" ]] && pass "guide page HTTP 200" || fail "guide page HTTP $status (expected 200)"

if grep -qi '>Error<' <<<"$body" || grep -qi 'class="error' <<<"$body"; then
  fail "visible Error on guide page"
else
  pass "no visible Error"
fi

title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
[[ "$title" == *"USDT to Bank Card Guide"* && "$title" == *"Exswaping"* ]] && pass "title present" || fail "title missing or wrong: $title"

desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
[[ "$desc" == *"bank card"* && "$desc" == *"USDT"* ]] && pass "meta description present" || fail "meta description missing or wrong"

h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
[[ "$h1c" == "1" ]] && grep -qi 'USDT to Bank Card Guide' <<<"$body" && pass "exactly one H1" || fail "expected 1 H1, found $h1c"

grep -qi 'rel="canonical" href="https://exswaping.com/en/guides/usdt-to-bank-card"' <<<"$body" && pass "canonical self-reference" || fail "canonical missing or wrong"

if grep -qi 'robots" content="index,follow' <<<"$body"; then
  pass "robots index,follow"
elif grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
  fail "guide page accidentally noindexed"
else
  pass "guide page indexable (no noindex)"
fi

grep -qi '<h2' <<<"$body" && pass "SSR body rendered (h2 present)" || fail "SSR body missing (no h2)"

grep -qi '"@type":"Article"' <<<"$body" && pass "Article schema present" || fail "Article schema missing"
grep -qi 'BreadcrumbList' <<<"$body" && pass "BreadcrumbList schema present" || fail "BreadcrumbList schema missing"
grep -qi 'FAQPage' <<<"$body" && grep -qi '<h2 id="faq">FAQ</h2>' <<<"$body" && pass "FAQPage schema with visible FAQ" || fail "FAQPage schema or FAQ content missing"

grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"aggregateRating"|"reviewRating"' <<<"$body" && fail "forbidden schema types present" || pass "no forbidden schema types"

python3 -c "
import sys,re,json
html=sys.stdin.read()
m=re.search(r'<script type=\"application/ld\\+json\"[^>]*>(.*?)</script>', html, re.S)
if not m: sys.exit(1)
json.loads(m.group(1))
" <<<"$body" 2>/dev/null && pass "JSON-LD parses valid" || fail "JSON-LD invalid or missing"

for path in \
  "/en/guides/usdt-exchange" \
  "/en/guides/usdt-trc20-exchange" \
  "/en/faq" \
  "/en/contacts" \
  "/en/pages/AMLKYC" \
  "/en/pages/instructions"; do
  grep -q "$path" <<<"$body" && pass "internal link present $path" || fail "missing internal link $path"
done

for pair in CARDRUB KSPBKZT; do
  path="/en/exchange/USDTTRC20/${pair}"
  grep -q "$path" <<<"$body" && pass "payout pair link present $path" || fail "missing payout pair link $path"
  [[ "$(fetch_status "${BASE_URL}${path}")" == "200" ]] && pass "payout pair reachable $path" || fail "payout pair not 200 $path"
done

for section in \
  "What it means to exchange USDT to a bank card" \
  "How the process works" \
  "Supported payout directions" \
  "Processing times" \
  "Fees and confirmations" \
  "Security considerations" \
  "AML and verification"; do
  grep -qi "$section" <<<"$body" && pass "required section: $section" || fail "missing section: $section"
done

for parent in usdt-exchange usdt-trc20-exchange; do
  pst="$(fetch_status "${BASE_URL}/en/guides/${parent}")"
  [[ "$pst" == "200" ]] && pass "cluster guide HTTP 200 /en/guides/${parent}" || fail "cluster guide HTTP $pst for ${parent}"
done

en_ex="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
grep -qi 'robots" content="[^"]*noindex' <<<"$en_ex" && pass "EN exchange still noindex" || fail "EN exchange missing noindex"

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  [[ "$count" == "198" ]] && pass "sitemap count unchanged (198)" || fail "sitemap count $count (expected 198)"
  grep -q '/en/guides/usdt-to-bank-card' "$SITEMAP_PATH" && fail "guide unexpectedly in sitemap" || pass "guide not in sitemap (expected)"
else
  fail "sitemap file missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "EN_BANK_CARD_GUIDE_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT)"
  exit 2
fi

echo "EN_BANK_CARD_GUIDE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
