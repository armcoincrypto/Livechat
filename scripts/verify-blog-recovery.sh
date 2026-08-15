#!/usr/bin/env bash
#
# SEO-9D.3 — Content quality recovery validation.
#
# Usage:
#   bash scripts/verify-blog-recovery.sh
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

log() { echo "[blog-recovery] $*" >&2; }
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
  python3 -c "import re,sys; html=sys.stdin.read(); m=re.search(r'news-seo9d3-ssr[^>]*>([\s\S]*?)</div>\s*</div>', html) or re.search(r'news-seo9d-ssr-wrap[^>]*>([\s\S]{0,12000})', html); print(m.group(1) if m else '')"
}

count_cyrillic() {
  python3 -c "import re,sys; t=sys.stdin.read(); cyr=len(re.findall(r'[\u0400-\u04FF]', t)); lat=len(re.findall(r'[A-Za-z]', t)); print(f'{cyr} {lat}')"
}

strip_tags_words() {
  python3 -c "import re,sys; html=sys.stdin.read(); text=re.sub(r'<script[^>]*>[\\s\\S]*?</script>',' ',html,flags=re.I); text=re.sub(r'<style[^>]*>[\\s\\S]*?</style>',' ',text,flags=re.I); text=re.sub(r'<[^>]+>',' ',text); words=[w for w in re.split(r'\\s+',text) if len(w)>2]; print(len(words))"
}

# Batch A — Article #20 Russian only on RU URL
slug20="exswaping-oficialno-dobavlen-v-monitoring-bestchange-20"
ru20="${BASE_URL}/ru/blog/${slug20}"
body20="$(fetch_body "$ru20")"
if [[ "$(fetch_status "$ru20")" == "200" ]]; then pass "#20 RU HTTP 200"; else fail "#20 RU HTTP not 200"; fi

read -r cyr20 lat20 <<<"$(printf '%s' "$body20" | extract_ssr_chunk | count_cyrillic)"
if [[ "$cyr20" -gt 400 && "$cyr20" -gt "$lat20" ]]; then
  pass "#20 RU predominantly Russian (cyr=$cyr20 lat=$lat20)"
else
  fail "#20 RU mixed-language (cyr=$cyr20 lat=$lat20)"
fi

if grep -qi 'Introduction — why a BestChange listing matters' <<<"$body20"; then
  fail "#20 RU still contains legacy English H2"
else
  pass "#20 RU legacy English block removed"
fi

if grep -qi 'мониторинг BestChange' <<<"$body20" && grep -qi 'Article' <<<"$body20"; then
  pass "#20 RU title/meta/schema intact"
else
  fail "#20 RU SEO elements missing"
fi

# Batch B — EN URLs must not serve RU body
EN_ARTICLES=(
  "rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2|Ruble Cards|Guide for Exchanging"
  "rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7|Scams|Protect Your Cryptocurrency"
  "amlkyc-i-vasa-bezopasnost-obieiasnenie-politiki-po-borbe-s-otmyvaniem-deneg-i-kyc-8|AML/KYC|Anti-Money Laundering"
  "exswaping-polnaia-instrukciia-10|Complete Guide|Complete Guide"
  "kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11|USDT to Rubles|Exchange USDT to Rubles"
  "exswaping-teper-na-cryptoru-12|Crypto.ru|Crypto.ru"
  "exswaping-teper-na-exchangesumo-15|Exchangesumo|Exchangesumo"
  "exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17|Exnode|Exnode"
  "exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la-19|Los Angeles|Los Angeles"
)

for entry in "${EN_ARTICLES[@]}"; do
  IFS='|' read -r slug title_need h1_need <<<"$entry"
  url="${BASE_URL}/en/blog/${slug}"
  body="$(fetch_body "$url")"
  if [[ "$(fetch_status "$url")" != "200" ]]; then fail "EN $slug HTTP not 200"; continue; fi
  read -r ecyr elat <<<"$(printf '%s' "$body" | extract_ssr_chunk | count_cyrillic)"
  if [[ "$ecyr" -gt 80 ]]; then
    fail "EN $slug serves RU body (cyr=$ecyr)"
  else
    pass "EN $slug English body (cyr=$ecyr)"
  fi
  title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
  if [[ "$title" == *"$title_need"* ]]; then pass "EN $slug English title"; else fail "EN $slug title wrong: $title"; fi
  if grep -qi "<h1[^>]*>[^<]*${h1_need}" <<<"$body"; then pass "EN $slug English H1"; else fail "EN $slug H1 not English"; fi
done

# Batch C — thin SSR articles
THIN_IDS=(
  "3|rukovodstvo-po-obmenu-kriptovaliuty-na-ukrainskie-karty-s-exswaping-3|украинские карты"
  "4|rukovodstvo-po-obmenu-kriptovaliuty-na-kriptovaliutu-s-exswaping-4|криптовалюту"
  "5|exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena-5|популярные"
  "6|kriptovaliuta-dlia-novickov-vvedenie-v-osnovy-kriptovaliut-blokcein-nft-i-defi-6|новичков"
  "9|zakony-i-regulirovanie-obnovleniia-zakonodatelstva-o-kriptovaliute-v-raznyx-stranax-9|регулирование"
  "13|usdt-to-amd-13|AMD"
  "14|usdt-to-kzt-14|KZT"
  "16|top-10-obmennikov-2025-goda-lucsie-servisy-dlia-bystrogo-i-vygodnogo-obmena-kriptovaliuty-16|2025"
  "18|p2p-obmeny-v-rossii-pocemu-rastut-zaderzki-i-kak-exswaping-zashhishhaet-klientov-18|P2P"
)

for entry in "${THIN_IDS[@]}"; do
  IFS='|' read -r id slug needle <<<"$entry"
  url="${BASE_URL}/ru/blog/${slug}"
  body="$(fetch_body "$url")"
  if [[ "$(fetch_status "$url")" != "200" ]]; then fail "#$id HTTP not 200"; continue; fi
  title="$(grep -oiE '<title>[^<]+' <<<"$body" | head -1 | sed 's/<title>//i')"
  if [[ -n "$title" && "$title" != *"Лучшие курсы"* ]]; then pass "#$id SSR title present"; else fail "#$id SSR title empty/generic"; fi
  h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
  if [[ "$h1c" -ge 1 ]]; then pass "#$id H1 present"; else fail "#$id H1 missing"; fi
  words="$(printf '%s' "$body" | extract_ssr_chunk | strip_tags_words)"
  if [[ "$words" -ge 120 ]]; then pass "#$id visible SSR body ($words words)"; else fail "#$id thin SSR body ($words words)"; fi
  if grep -qi '"@type":"Article"' <<<"$body" || grep -qi '"@type": "Article"' <<<"$body"; then pass "#$id Article schema"; else fail "#$id Article schema missing"; fi
  canon="https://exswaping.com/ru/blog/${slug}"
  if grep -qi "rel=\"canonical\" href=\"${canon}\"" <<<"$body"; then pass "#$id canonical"; else fail "#$id canonical missing"; fi
  if grep -qi "$needle" <<<"$body"; then pass "#$id content marker"; else warn "#$id content marker weak"; fi
done

# Batch D — Article #15 single H1
slug15="exswaping-teper-na-exchangesumo-15"
body15="$(fetch_body "${BASE_URL}/ru/blog/${slug15}")"
h15="$(grep -oi '<h1' <<<"$body15" | wc -l | tr -d ' ')"
if [[ "$h15" -eq 1 ]]; then pass "#15 single H1 ($h15)"; else fail "#15 multiple H1 ($h15)"; fi

# Trust links on optimized articles (sample)
TRUST_SLUGS=(
  "exswaping-oficialno-dobavlen-v-monitoring-bestchange-20"
  "exswaping-teper-na-exchangesumo-15"
  "rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2"
)
for slug in "${TRUST_SLUGS[@]}"; do
  body="$(fetch_body "${BASE_URL}/ru/blog/${slug}")"
  missing=0
  for link in "/ru/" "/ru/faq" "/ru/contacts" "/ru/pages/instructions" "/ru/pages/AMLKYC"; do
    if ! grep -q "$link" <<<"$body"; then missing=$((missing + 1)); fi
  done
  if [[ "$missing" -eq 0 ]]; then pass "$slug trust links complete"; else fail "$slug missing $missing trust links"; fi
done

# Regression guards
if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  if [[ "$count" == "198" ]]; then pass "sitemap count 198"; else fail "sitemap count $count"; fi
else
  fail "sitemap missing"
fi

for guide_url in "$GUIDE_RUB" "$GUIDE_TRC20"; do
  if [[ "$(fetch_status "$guide_url")" == "200" ]]; then pass "guide OK $guide_url"; else fail "guide fail $guide_url"; fi
done

if [[ "$(fetch_status "${BASE_URL}/ru/xyzrandom404test")" == "404" ]]; then pass "soft 404 still 404"; else fail "soft 404 broken"; fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "BLOG_RECOVERY_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "BLOG_RECOVERY_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "BLOG_RECOVERY_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
