#!/usr/bin/env bash
# SEO-AUTHORITY-2 — Trust hub validation for /ru/guides/bezopasnyj-kriptoobmen
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
GUIDE_URL="${BASE_URL}/ru/guides/bezopasnyj-kriptoobmen"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
MIN_WORDS=2500
MAX_WORDS=4000

FAIL=0
log(){ echo "[authority2-trust] $*" >&2; }
pass(){ log "PASS $*"; }
fail(){ log "FAIL $*"; FAIL=$((FAIL+1)); }

fetch_status(){ curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'; }
fetch_body(){ curl -sS --max-time 60 "$1" || true; }
word_count_guide(){
  python3 -c "
import re,sys
h=sys.stdin.read()
m=re.search(r'class=\"guide-content[^\"]*\"[^>]*>([\s\S]*?)</div>\s*(?:</div>|<div class=\"guide-cta)', h)
if not m:
    m=re.search(r'class=\"prose my-6 formatting__text[^\"]*\"[^>]*>([\s\S]*?)</div>', h)
chunk=m.group(1) if m else h
t=re.sub(r'<[^>]+>',' ', chunk)
print(len([w for w in re.split(r'\s+',t) if len(w)>2]))
"
}

body="$(fetch_body "$GUIDE_URL")"
status="$(fetch_status "$GUIDE_URL")"

[[ "$status" == "200" ]] && pass "HTTP 200" || fail "HTTP $status"
grep -qi 'Error page\|<not-found\|>Error<' <<<"$body" && fail "error page visible" || pass "no error page"
grep -qi '<h2' <<<"$body" && pass "SSR rendered (h2 present)" || fail "SSR missing"
grep -qi 'robots" content="[^"]*noindex' <<<"$body" && fail "noindex present" || pass "index,follow"
grep -qi 'rel="canonical" href="https://exswaping.com/ru/guides/bezopasnyj-kriptoobmen"' <<<"$body" && pass "self canonical" || fail "canonical missing"
h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
[[ "$h1c" == "1" ]] && pass "one H1" || fail "H1 count $h1c"
grep -qi 'Безопасный обмен криптовалют' <<<"$body" && pass "H1 topic present" || fail "H1 topic missing"

title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
[[ "$title" == *"Безопасный обмен"* && "$title" == *"Exswaping"* ]] && pass "title OK" || fail "title wrong: $title"

grep -qi '"@type":"Article"' <<<"$body" && pass "Article schema" || fail "Article missing"
grep -qi 'BreadcrumbList' <<<"$body" && pass "BreadcrumbList schema" || fail "BreadcrumbList missing"
grep -qi 'FAQPage' <<<"$body" && pass "FAQPage schema" || fail "FAQPage missing"
grep -qi '<h2 id="faq">FAQ</h2>' <<<"$body" && pass "visible FAQ block" || fail "visible FAQ missing"
grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"aggregateRating"' <<<"$body" && fail "forbidden schema" || pass "no forbidden schema"

words="$(printf '%s' "$body" | word_count_guide)"
[[ "$words" -ge "$MIN_WORDS" && "$words" -le "$MAX_WORDS" ]] && pass "words=$words (range $MIN_WORDS-$MAX_WORDS)" || fail "words=$words out of range"

for path in \
  "/ru/guides/obmen-usdt-na-rubli" \
  "/ru/guides/obmen-usdt-trc20" \
  "/ru/guides/obmen-usdt-na-kartu" \
  "/ru/guides/usdt-trc20-i-erc20" \
  "/ru/faq" \
  "/ru/contacts" \
  "/ru/pages/AMLKYC" \
  "/ru/pages/instructions"; do
  grep -q "$path" <<<"$body" && pass "link $path" || fail "missing link $path"
done

for blog in \
  "exswaping-oficialno-dobavlen-v-monitoring-bestchange-20" \
  "exswaping-teper-na-cryptoru-12" \
  "exswaping-teper-na-exnode" \
  "exswaping-teper-na-exchangesumo"; do
  grep -q "/ru/blog/${blog}" <<<"$body" && pass "monitoring blog link $blog" || fail "missing monitoring blog $blog"
done

for section in \
  "Как выбрать сервис обмена" \
  "Красные флаги" \
  "AML и KYC" \
  "Мониторинги обменников" \
  "Как проверить репутацию" \
  "Чеклист безопасности" \
  "Типичные ошибки"; do
  grep -qi "$section" <<<"$body" && pass "section: $section" || fail "missing section: $section"
done

for g in obmen-usdt-na-rubli obmen-usdt-trc20 obmen-usdt-na-kartu usdt-trc20-i-erc20; do
  [[ "$(fetch_status "${BASE_URL}/ru/guides/${g}")" == "200" ]] && pass "cluster guide 200 $g" || fail "cluster $g not 200"
done

[[ "$(fetch_status "${BASE_URL}/ru/xyzrandom404test")" == "404" ]] && pass "soft 404 preserved" || fail "soft 404 broken"
en_ex="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
grep -qi 'robots" content="[^"]*noindex' <<<"$en_ex" && pass "EN exchange noindex preserved" || fail "EN exchange noindex lost"

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  [[ "$count" == "198" ]] && pass "sitemap remains 198" || fail "sitemap count $count"
  grep -q '/ru/guides/bezopasnyj-kriptoobmen' "$SITEMAP_PATH" && fail "guide unexpectedly in sitemap" || pass "guide not in sitemap (deferred policy)"
  grep -q '/ru/pages/bezopasnyj-kriptoobmen' "$SITEMAP_PATH" && fail "CMS alias in sitemap" || pass "CMS alias not in sitemap"
else
  fail "sitemap missing"
fi

if [[ "$FAIL" -gt 0 ]]; then echo "SEO_AUTHORITY_2_STATUS=FAIL failures=$FAIL" >&2; exit 2; fi
echo "SEO_AUTHORITY_2_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
