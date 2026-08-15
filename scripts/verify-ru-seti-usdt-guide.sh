#!/usr/bin/env bash
# SEO-AUTHORITY-4 — USDT networks mega-hub validation
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
GUIDE_URL="${BASE_URL}/ru/guides/seti-usdt"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
MIN_WORDS=3000
MAX_WORDS=4500

FAIL=0
log(){ echo "[authority4-networks] $*" >&2; }
pass(){ log "PASS $*"; }
fail(){ log "FAIL $*"; FAIL=$((FAIL+1)); }

fetch_status(){ curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'; }
fetch_body(){ curl -sS --max-time 60 "$1" || true; }
word_count_guide(){
  python3 -c "
import re,sys
h=sys.stdin.read()
m=re.search(r'class=\"guide-content[^\"]*\"[^>]*>([\s\S]*?)</div>\s*(?:</div>|<div class=\"guide-cta)', h)
chunk=m.group(1) if m else ''
t=re.sub(r'<[^>]+>',' ', chunk)
print(len([w for w in re.split(r'\s+',t) if len(w)>2]))
"
}

body="$(fetch_body "$GUIDE_URL")"
status="$(fetch_status "$GUIDE_URL")"

[[ "$status" == "200" ]] && pass "HTTP 200" || fail "HTTP $status"
grep -qi 'Error page\|<not-found\|>Error<' <<<"$body" && fail "error page" || pass "no error page"
grep -qi '<h2' <<<"$body" && pass "SSR rendered" || fail "SSR missing"
grep -qi 'robots" content="[^"]*noindex' <<<"$body" && fail "noindex" || pass "index,follow"
grep -qi 'rel="canonical" href="https://exswaping.com/ru/guides/seti-usdt"' <<<"$body" && pass "self canonical" || fail "canonical"
[[ "$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')" == "1" ]] && pass "one H1" || fail "H1 count"
grep -qi 'Сети USDT' <<<"$body" && pass "H1 topic" || fail "H1 topic"

title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
[[ "$title" == *"USDT"* && "$title" == *"Exswaping"* ]] && pass "title OK" || fail "title: $title"

grep -qi '"@type":"Article"' <<<"$body" && pass "Article schema" || fail "Article"
grep -qi 'BreadcrumbList' <<<"$body" && pass "BreadcrumbList" || fail "BreadcrumbList"
grep -qi 'FAQPage' <<<"$body" && pass "FAQPage" || fail "FAQPage"
grep -qi '<h2 id="faq">FAQ</h2>' <<<"$body" && pass "visible FAQ" || fail "visible FAQ"
grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"aggregateRating"' <<<"$body" && fail "forbidden schema" || pass "no forbidden schema"
grep -qiE 'лучший обменник|лучшая платформа|лучшая сеть|лучший мониторинг' <<<"$body" && fail "superlative ranking language" || pass "no ranking superlatives"

words="$(printf '%s' "$body" | word_count_guide)"
[[ "$words" -ge "$MIN_WORDS" && "$words" -le "$MAX_WORDS" ]] && pass "words=$words" || fail "words=$words out of range"

for g in obmen-usdt-trc20 usdt-trc20-i-erc20 obmen-usdt-na-kartu obmen-usdt-na-rubli; do
  grep -q "/ru/guides/${g}" <<<"$body" || fail "missing guide $g"
done
pass "commercial guide links"
grep -q '/ru/guides/bezopasnyj-kriptoobmen' <<<"$body" && pass "trust hub link" || fail "missing trust hub"
grep -q '/ru/guides/monitoring-kriptovalyutnyh-obmennikov' <<<"$body" && pass "monitoring hub link" || fail "missing monitoring hub"
for p in /ru/faq /ru/contacts /ru/pages/AMLKYC /ru/pages/instructions; do
  grep -q "$p" <<<"$body" || fail "missing $p"
done
pass "trust resource links"

for section in \
  "Что такое USDT" \
  "нескольких блокчейнах" \
  "TRC20" \
  "ERC20" \
  "BEP20" \
  "комиссий" \
  "подтвержден" \
  "Совместимость кошельков" \
  "выбрать подходящую сеть" \
  "Типичные ошибки"; do
  grep -qi "$section" <<<"$body" && pass "section: $section" || fail "missing section: $section"
done

[[ "$(fetch_status "${BASE_URL}/ru/guides/monitoring-kriptovalyutnyh-obmennikov")" == "200" ]] && pass "monitoring hub 200" || fail "monitoring hub not 200"
[[ "$(fetch_status "${BASE_URL}/ru/xyzrandom404test")" == "404" ]] && pass "soft 404" || fail "soft 404"
en_ex="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
grep -qi 'robots" content="[^"]*noindex' <<<"$en_ex" && pass "EN exchange noindex" || fail "EN noindex lost"

count="$(grep -c '<url>' "$SITEMAP_PATH")"
[[ "$count" == "198" ]] && pass "sitemap 198" || fail "sitemap $count"
grep -q '/ru/guides/seti-usdt' "$SITEMAP_PATH" && fail "guide in sitemap" || pass "guide deferred"

if [[ "$FAIL" -gt 0 ]]; then echo "SEO_AUTHORITY_4_STATUS=FAIL failures=$FAIL" >&2; exit 2; fi
echo "SEO_AUTHORITY_4_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
