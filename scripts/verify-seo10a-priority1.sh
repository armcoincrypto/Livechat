#!/usr/bin/env bash
# SEO-10A — Priority 1 + authority flow validation
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
FAIL=0
pass() { echo "[seo10a] PASS $*"; }
fail() { echo "[seo10a] FAIL $*"; FAIL=$((FAIL + 1)); }

fetch_body() { curl -sS --max-time 60 "$1" || true; }

word_count() {
  python3 -c "import re,sys; t=sys.stdin.read(); t=re.sub(r'<[^>]+>',' ',t); print(len([w for w in re.split(r'\s+',t) if len(w)>2]))"
}

P1_IDS=(2 7 8 10 11 13 14)
P1_SLUGS=(
  "rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2"
  "rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7"
  "amlkyc-i-vasa-bezopasnost-obieiasnenie-politiki-po-borbe-s-otmyvaniem-deneg-i-kyc-8"
  "exswaping-polnaia-instrukciia-10"
  "kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11"
  "usdt-to-amd-13"
  "usdt-to-kzt-14"
)

RU_CLUSTER=(obmen-usdt-na-rubli obmen-usdt-trc20 obmen-usdt-na-kartu usdt-trc20-i-erc20)
EN_CLUSTER=(usdt-exchange usdt-trc20-exchange usdt-to-bank-card)

# Phase 1: all 38 blog URLs cluster + trust coverage
mapfile -t BLOG_URLS < <(grep -oE 'https://exswaping.com/(ru|en)/blog/[^<]+' public/static/seo/sitemap.xml | sort -u)
RU_OK=0
EN_OK=0
for url in "${BLOG_URLS[@]}"; do
  body="$(fetch_body "$url")"
  loc="ru"; [[ "$url" == */en/* ]] && loc="en"
  if [[ "$loc" == "ru" ]]; then
    ok=1
    for g in "${RU_CLUSTER[@]}"; do
      grep -q "/ru/guides/${g}" <<<"$body" || ok=0
    done
    grep -q 'news-seo10a-cluster\|/ru/guides/obmen-usdt-na-rubli' <<<"$body" || ok=0
    grep -q '/ru/pages/instructions' <<<"$body" || ok=0
    grep -q '/ru/pages/AMLKYC' <<<"$body" || ok=0
    grep -q '/ru/faq' <<<"$body" || ok=0
    grep -q '/ru/contacts' <<<"$body" || ok=0
    if [[ "$ok" -eq 1 ]]; then RU_OK=$((RU_OK + 1)); pass "RU funnel $url"; else fail "RU funnel $url"; fi
  else
    ok=1
    for g in "${EN_CLUSTER[@]}"; do
      grep -q "/en/guides/${g}" <<<"$body" || ok=0
    done
    grep -q '/en/pages/instructions' <<<"$body" || ok=0
    grep -q '/en/pages/AMLKYC' <<<"$body" || ok=0
    grep -q '/en/faq' <<<"$body" || ok=0
    grep -q '/en/pages/contacts' <<<"$body" || ok=0
    if [[ "$ok" -eq 1 ]]; then EN_OK=$((EN_OK + 1)); pass "EN funnel $url"; else fail "EN funnel $url"; fi
  fi
done
[[ "$RU_OK" -eq 19 ]] || fail "RU funnel coverage $RU_OK/19"
[[ "$EN_OK" -eq 19 ]] || fail "EN funnel coverage $EN_OK/19"

# Phase 2+3: Priority 1 depth + FAQ schema
for slug in "${P1_SLUGS[@]}"; do
  for loc in ru en; do
    url="${BASE_URL}/${loc}/blog/${slug}"
    body="$(fetch_body "$url")"
    chunk="$(python3 -c "import re,sys; h=sys.stdin.read(); m=re.search(r'news-seo9d3-ssr[^>]*>([\s\S]*?)</div>\s*</div>', h); print(m.group(1) if m else '')" <<<"$body")"
    words="$(printf '%s' "$chunk" | word_count)"
    if [[ "$words" -ge 1500 ]]; then pass "$loc/$slug words=$words"; else fail "$loc/$slug words=$words (<1500)"; fi
    if grep -qi '"@type":"FAQPage"' <<<"$body" || grep -qi '"@type": "FAQPage"' <<<"$body"; then
      pass "$loc/$slug FAQPage schema"
    else
      fail "$loc/$slug FAQPage schema missing"
    fi
    if grep -qi '"@type":"Article"' <<<"$body"; then pass "$loc/$slug Article schema"; else fail "$loc/$slug Article missing"; fi
    if grep -qi 'BreadcrumbList' <<<"$body"; then pass "$loc/$slug Breadcrumb schema"; else fail "$loc/$slug Breadcrumb missing"; fi
  done
done

# Sitemap unchanged
count="$(grep -c '<url>' public/static/seo/sitemap.xml)"
[[ "$count" == "198" ]] && pass "sitemap 198" || fail "sitemap $count"

# Run base portfolio check
if bash scripts/verify-blog-portfolio-seo.sh; then pass "blog-portfolio baseline"; else fail "blog-portfolio baseline"; fi

if [[ "$FAIL" -gt 0 ]]; then
  echo "SEO10A_STATUS=FAIL failures=$FAIL" >&2
  exit 2
fi
echo "SEO10A_STATUS=PASS" >&2
exit 0
