#!/usr/bin/env bash
#
# SEO-9D.4 — Full blog portfolio validation (38 sitemap URLs).
#
# Usage:
#   bash scripts/verify-blog-portfolio-seo.sh
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
GUIDE_RUB="${BASE_URL}/ru/guides/obmen-usdt-na-rubli"
GUIDE_TRC20="${BASE_URL}/ru/guides/obmen-usdt-trc20"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[blog-portfolio] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

extract_ssr_chunk() {
  python3 -c "import re,sys; html=sys.stdin.read(); m=re.search(r'news-seo9d3-ssr[^>]*>([\s\S]*?)</div>\s*</div>', html) or re.search(r'news-seo9d-ssr-wrap[^>]*>([\s\S]{0,15000})', html); print(m.group(1) if m else '')"
}

count_cyrillic() {
  python3 -c "import re,sys; t=sys.stdin.read(); print(len(re.findall(r'[\u0400-\u04FF]', t)), len(re.findall(r'[A-Za-z]', t)))"
}

word_count() {
  python3 -c "import re,sys; t=sys.stdin.read(); t=re.sub(r'<[^>]+>',' ',t); print(len([w for w in re.split(r'\s+',t) if len(w)>2]))"
}

mapfile -t BLOG_URLS < <(grep -oE 'https://exswaping.com/(ru|en)/blog/[^<]+' "$SITEMAP_PATH" | sort -u)
TOTAL="${#BLOG_URLS[@]}"

if [[ "$TOTAL" != "38" ]]; then
  fail "expected 38 blog URLs in sitemap, found $TOTAL"
else
  pass "blog URL inventory count 38"
fi

RU_CLEAN=0
EN_CLEAN=0

for url in "${BLOG_URLS[@]}"; do
  slug="${url##*/}"
  loc="ru"
  [[ "$url" == */en/* ]] && loc="en"

  body="$(fetch_body "$url")"
  status="$(fetch_status "$url")"

  if [[ "$status" != "200" ]]; then
    fail "$loc/$slug HTTP $status (expected 200)"
    continue
  fi
  pass "$loc/$slug HTTP 200"

  title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i' | xargs || true)"
  if [[ -n "$title" && "$title" != *"Лучшие курсы"* ]]; then
    pass "$loc/$slug title present"
  else
    fail "$loc/$slug title missing or generic"
  fi

  desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i' || true)"
  if [[ -n "$desc" && "$desc" != *"Лучшие курсы"* ]]; then
    pass "$loc/$slug meta description present"
  else
    fail "$loc/$slug meta description missing or generic"
  fi

  h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
  if [[ "$h1c" -eq 1 ]]; then
    pass "$loc/$slug single H1"
  else
    fail "$loc/$slug H1 count $h1c (expected 1)"
  fi

  canon="https://exswaping.com/${loc}/blog/${slug}"
  if grep -qi "rel=\"canonical\" href=\"${canon}\"" <<<"$body"; then
    pass "$loc/$slug canonical"
  else
    fail "$loc/$slug canonical missing"
  fi

  if grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
    fail "$loc/$slug unexpectedly noindexed"
  else
    pass "$loc/$slug indexable"
  fi

  if grep -qi '"@type":"Article"' <<<"$body" || grep -qi '"@type": "Article"' <<<"$body"; then
    pass "$loc/$slug Article schema"
  else
    fail "$loc/$slug Article schema missing"
  fi

  if grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"@type": "(Product|LocalBusiness|Review)"|"aggregateRating"|"reviewRating"' <<<"$body"; then
    fail "$loc/$slug forbidden schema present"
  else
    pass "$loc/$slug no forbidden schema"
  fi

  chunk="$(printf '%s' "$body" | extract_ssr_chunk)"
  words="$(printf '%s' "$chunk" | word_count)"
  if [[ "$words" -ge 80 ]]; then
    pass "$loc/$slug visible body ($words words)"
  else
    fail "$loc/$slug thin visible body ($words words)"
  fi

  read -r cyr lat <<<"$(printf '%s' "$chunk" | count_cyrillic)"
  if [[ "$loc" == "ru" ]]; then
    if [[ "$cyr" -gt 100 && "$cyr" -gt "$lat" ]]; then
      pass "$loc/$slug Russian body (cyr=$cyr)"
      RU_CLEAN=$((RU_CLEAN + 1))
    else
      fail "$loc/$slug language mismatch (cyr=$cyr lat=$lat)"
    fi
  else
    if [[ "$lat" -gt 100 && "$lat" -gt "$cyr" ]]; then
      pass "$loc/$slug English body (lat=$lat)"
      EN_CLEAN=$((EN_CLEAN + 1))
    else
      fail "$loc/$slug language mismatch (cyr=$cyr lat=$lat)"
    fi
  fi
done

pass "RU language clean count $RU_CLEAN/19"
pass "EN language clean count $EN_CLEAN/19"
[[ "$RU_CLEAN" -eq 19 ]] || fail "RU language clean expected 19 got $RU_CLEAN"
[[ "$EN_CLEAN" -eq 19 ]] || fail "EN language clean expected 19 got $EN_CLEAN"

for guide_url in "$GUIDE_RUB" "$GUIDE_TRC20"; do
  if [[ "$(fetch_status "$guide_url")" == "200" ]]; then pass "guide OK $guide_url"; else fail "guide fail $guide_url"; fi
done

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  if [[ "$count" == "198" ]]; then pass "sitemap count 198"; else fail "sitemap count $count"; fi
else
  fail "sitemap missing"
fi

if [[ "$(fetch_status "${BASE_URL}/ru/xyzrandom404test")" == "404" ]]; then pass "soft 404 still 404"; else fail "soft 404 broken"; fi
if [[ "$(fetch_status "${BASE_URL}/ru/exchange/INVALID/PAIR")" == "404" ]]; then pass "invalid exchange still 404"; else fail "invalid exchange broken"; fi

en_ex="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
if grep -qi 'robots" content="[^"]*noindex' <<<"$en_ex"; then pass "EN exchange noindex preserved"; else fail "EN exchange noindex missing"; fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "BLOG_PORTFOLIO_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "BLOG_PORTFOLIO_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "BLOG_PORTFOLIO_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
