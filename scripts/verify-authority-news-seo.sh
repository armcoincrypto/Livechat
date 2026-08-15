#!/usr/bin/env bash
#
# SEO-9D.1 — Validation for optimized authority/news listing batch (5 articles).
#
# Usage:
#   bash scripts/verify-authority-news-seo.sh
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
GUIDE_URL="${BASE_URL}/ru/guides/obmen-usdt-na-rubli"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[authority-news] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

ARTICLES=(
  "exswaping-oficialno-dobavlen-v-monitoring-bestchange-20|BestChange|мониторинге BestChange|/ru/pages/instructions"
  "exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17|Exnode|платформе Exnode|/ru/faq"
  "exswaping-teper-na-cryptoru-12|Crypto.ru|Crypto.ru|/ru/contacts"
  "exswaping-teper-na-exchangesumo-15|Exchangesumo|Exchangesumo|/ru/pages/AMLKYC"
  "exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la-19|Crypto to Cash LA|Лос-Анджелесе|/ru/pages/instructions"
)

for entry in "${ARTICLES[@]}"; do
  IFS='|' read -r slug title_need desc_need link_need <<<"$entry"
  url="${BASE_URL}/ru/blog/${slug}"
  body="$(fetch_body "$url")"
  status="$(fetch_status "$url")"

  if [[ "$status" == "200" ]]; then
    pass "$slug HTTP 200"
  else
    fail "$slug HTTP $status (expected 200)"
    continue
  fi

  title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
  if [[ -n "$title" && "$title" == *"$title_need"* ]]; then
    pass "$slug title present"
  else
    fail "$slug title missing or wrong: $title"
  fi

  desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
  if [[ -n "$desc" && "$desc" == *"$desc_need"* ]]; then
    pass "$slug meta description present"
  else
    fail "$slug meta description missing or weak"
  fi

  h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
  if [[ "$h1c" -ge 1 ]]; then
    pass "$slug H1 present ($h1c)"
  else
    fail "$slug H1 missing"
  fi

  canon="https://exswaping.com/ru/blog/${slug}"
  if grep -qi "rel=\"canonical\" href=\"${canon}\"" <<<"$body"; then
    pass "$slug canonical self-reference"
  else
    fail "$slug canonical missing or wrong"
  fi

  if grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
    fail "$slug accidentally noindexed"
  else
    pass "$slug indexable (no noindex)"
  fi

  if grep -qi "$link_need" <<<"$body"; then
    pass "$slug internal link present ($link_need)"
  else
    fail "$slug missing internal link $link_need"
  fi

  if grep -qi '"@type":"Article"' <<<"$body" || grep -qi '"@type": "Article"' <<<"$body"; then
    pass "$slug Article schema present"
  else
    fail "$slug Article schema missing"
  fi

  if grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"@type": "(Product|LocalBusiness|Review)"|"aggregateRating"|"reviewRating"' <<<"$body"; then
    fail "$slug forbidden schema types present"
  else
    pass "$slug no forbidden schema types"
  fi
done

guide_body="$(fetch_body "$GUIDE_URL")"
guide_status="$(fetch_status "$GUIDE_URL")"
if [[ "$guide_status" == "200" ]]; then
  pass "guide page still HTTP 200"
else
  fail "guide page HTTP $guide_status"
fi

if grep -qi 'robots" content="[^"]*noindex' <<<"$guide_body"; then
  fail "guide page accidentally noindexed"
else
  pass "guide page still indexable"
fi

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
else
  fail "sitemap file missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "AUTHORITY_NEWS_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "AUTHORITY_NEWS_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "AUTHORITY_NEWS_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
