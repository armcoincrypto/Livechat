#!/usr/bin/env bash
#
# SEO-RU-10A — Validation for /ru/guides/usdt-trc20-i-erc20
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
GUIDE_URL="${BASE_URL}/ru/guides/usdt-trc20-i-erc20"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
log() { echo "[ru-trc20-erc20-guide] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() { curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'; }
fetch_body() { curl -sS --max-time 60 "$1" || true; }

body="$(fetch_body "$GUIDE_URL")"
status="$(fetch_status "$GUIDE_URL")"

[[ "$status" == "200" ]] && pass "HTTP 200" || fail "HTTP $status"

grep -qi '>Error<' <<<"$body" && fail "visible Error" || pass "no visible Error"

title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
[[ "$title" == *"USDT TRC20 и ERC20"* && "$title" == *"Exswaping"* ]] && pass "title OK" || fail "title wrong: $title"

desc="$(grep -oiE '<meta name="description" content="[^"]+' <<<"$body" | head -1 | sed 's/.*content="//i')"
[[ "$desc" == *"TRC20"* && "$desc" == *"ERC20"* ]] && pass "meta OK" || fail "meta wrong"

h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
[[ "$h1c" == "1" ]] && pass "one H1" || fail "H1 count $h1c"

grep -qi 'rel="canonical" href="https://exswaping.com/ru/guides/usdt-trc20-i-erc20"' <<<"$body" && pass "canonical self" || fail "canonical missing"

grep -qi 'robots" content="[^"]*noindex' <<<"$body" && fail "noindex present" || pass "indexable (no noindex)"

grep -qi '<h2' <<<"$body" && pass "SSR rendered" || fail "SSR missing"

grep -qi '"@type":"Article"' <<<"$body" && pass "Article schema" || fail "Article missing"
grep -qi 'BreadcrumbList' <<<"$body" && pass "BreadcrumbList schema" || fail "BreadcrumbList missing"
grep -qi 'FAQPage' <<<"$body" && grep -qi '<h2 id="faq">FAQ</h2>' <<<"$body" && pass "FAQPage schema" || fail "FAQPage missing"

grep -qiE '"@type":"(Product|LocalBusiness|Review)"|"aggregateRating"' <<<"$body" && fail "forbidden schema" || pass "no forbidden schema"

python3 -c "
import sys,re,json
html=sys.stdin.read()
m=re.search(r'<script type=\"application/ld\\+json\"[^>]*>(.*?)</script>', html, re.S)
if not m: sys.exit(1)
json.loads(m.group(1))
" <<<"$body" 2>/dev/null && pass "valid JSON-LD" || fail "invalid JSON-LD"

for path in \
  "/ru/guides/obmen-usdt-trc20" \
  "/ru/guides/obmen-usdt-na-kartu" \
  "/ru/guides/obmen-usdt-na-rubli" \
  "/ru/faq" \
  "/ru/contacts" \
  "/ru/pages/AMLKYC" \
  "/ru/pages/instructions"; do
  grep -q "$path" <<<"$body" && pass "link $path" || fail "missing link $path"
done

for section in \
  "Что такое USDT" \
  "Что такое TRC20" \
  "Что такое ERC20" \
  "Основные различия" \
  "Комиссии" \
  "Скорость подтверждений" \
  "Совместимость кошельков" \
  "Когда использовать TRC20" \
  "Когда использовать ERC20" \
  "Частые ошибки"; do
  grep -qi "$section" <<<"$body" && pass "section: $section" || fail "missing section: $section"
done

for g in obmen-usdt-na-rubli obmen-usdt-trc20 obmen-usdt-na-kartu; do
  [[ "$(fetch_status "${BASE_URL}/ru/guides/${g}")" == "200" ]] && pass "cluster $g 200" || fail "cluster $g not 200"
done

[[ "$(fetch_status "${BASE_URL}/ru/xyzrandom404test")" == "404" ]] && pass "soft 404" || fail "soft 404 broken"

en_ex="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
grep -qi 'robots" content="[^"]*noindex' <<<"$en_ex" && pass "EN exchange noindex preserved" || fail "EN exchange noindex lost"

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  [[ "$count" == "198" ]] && pass "sitemap 198" || fail "sitemap count $count"
  grep -q '/ru/guides/usdt-trc20-i-erc20' "$SITEMAP_PATH" && fail "guide in sitemap" || pass "guide not in sitemap"
else
  fail "sitemap missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "RU_TRC20_ERC20_GUIDE_STATUS=FAIL" >&2
  exit 2
fi
echo "RU_TRC20_ERC20_GUIDE_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
