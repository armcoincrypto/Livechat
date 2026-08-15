#!/usr/bin/env bash
# SEO-10C — Priority 3 validation
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"
BASE_URL="${BASE_URL:-https://exswaping.com}"
FAIL=0
pass(){ echo "[seo10c] PASS $*"; }
fail(){ echo "[seo10c] FAIL $*"; FAIL=$((FAIL+1)); }
fetch_body(){ curl -sS --max-time 60 "$1" || true; }
word_count(){ python3 -c "import re,sys; t=re.sub(r'<[^>]+>',' ',sys.stdin.read()); print(len([w for w in re.split(r'\s+',t) if len(w)>2]))"; }

P3=(
  "15:exswaping-teper-na-exchangesumo:News:800"
  "16:top-10-obmennikov-2025-goda-lucsie-servisy-dlia-bystrogo-i-vygodnogo-obmena-kriptovaliuty:Commercial:1500"
  "17:exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut:News:800"
  "19:exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la:News:800"
)

for entry in "${P3[@]}"; do
  IFS=':' read -r id slug class min <<<"$entry"
  for loc in ru en; do
    url="${BASE_URL}/${loc}/blog/${slug}-${id}"
    body="$(fetch_body "$url")"
    st="$(curl -sSI --max-time 45 "$url" | awk 'NR==1{print $2; exit}')"
    [[ "$st" == "200" ]] && pass "$loc/$slug HTTP 200" || fail "$loc/$slug HTTP $st"
    grep -qi 'Error page\|<not-found' <<<"$body" && fail "$loc/$slug error page" || pass "$loc/$slug no error page"
    grep -qi 'news-seo9d3-ssr' <<<"$body" && pass "$loc/$slug SSR body" || fail "$loc/$slug SSR body missing"
    grep -qi 'robots" content="[^"]*noindex' <<<"$body" && fail "$loc/$slug noindex" || pass "$loc/$slug index,follow"
    canon="https://exswaping.com/${loc}/blog/${slug}-${id}"
    grep -q "rel=\"canonical\" href=\"${canon}\"" <<<"$body" && pass "$loc/$slug self canonical" || fail "$loc/$slug canonical"
    h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
    [[ "$h1c" -eq 1 ]] && pass "$loc/$slug one H1" || fail "$loc/$slug H1=$h1c"
    grep -qi '"@type":"Article"' <<<"$body" && pass "$loc/$slug Article schema" || fail "$loc/$slug Article"
    grep -qi 'BreadcrumbList' <<<"$body" && pass "$loc/$slug BreadcrumbList" || fail "$loc/$slug BreadcrumbList"
    grep -qi 'FAQPage' <<<"$body" && pass "$loc/$slug FAQPage schema" || fail "$loc/$slug FAQPage"
    grep -qi 'id="faq"' <<<"$body" && pass "$loc/$slug visible FAQ block" || fail "$loc/$slug visible FAQ"
    chunk="$(python3 -c "import re,sys; h=sys.stdin.read(); m=re.search(r'news-seo9d3-ssr[^>]*>([\s\S]*?)</div>\s*</div>',h); print(m.group(1) if m else '')" <<<"$body")"
    words="$(printf '%s' "$chunk" | word_count)"
    [[ "$words" -ge "$min" ]] && pass "$loc/$slug words=$words (min=$min)" || fail "$loc/$slug words=$words (<$min)"
    if [[ "$loc" == "ru" ]]; then
      for g in obmen-usdt-na-rubli obmen-usdt-trc20 obmen-usdt-na-kartu usdt-trc20-i-erc20; do
        grep -q "/ru/guides/${g}" <<<"$body" || fail "$loc/$slug missing guide $g"
      done
      for p in /ru/faq /ru/contacts /ru/pages/AMLKYC /ru/pages/instructions; do
        grep -q "$p" <<<"$body" || fail "$loc/$slug missing $p"
      done
      pass "$loc/$slug RU internal links"
    else
      for g in usdt-exchange usdt-trc20-exchange usdt-to-bank-card; do
        grep -q "/en/guides/${g}" <<<"$body" || fail "$loc/$slug missing guide $g"
      done
      for p in /en/faq /en/pages/contacts /en/pages/AMLKYC /en/pages/instructions; do
        grep -q "$p" <<<"$body" || fail "$loc/$slug missing $p"
      done
      pass "$loc/$slug EN internal links"
    fi
  done
done

count="$(grep -c '<url>' public/static/seo/sitemap.xml)"
[[ "$count" == "198" ]] && pass "sitemap remains 198" || fail "sitemap $count"
bash scripts/verify-blog-portfolio-seo.sh && pass "blog portfolio baseline" || fail "blog portfolio baseline"

if [[ "$FAIL" -gt 0 ]]; then echo "SEO10C_STATUS=FAIL failures=$FAIL" >&2; exit 2; fi
echo "SEO10C_STATUS=PASS" >&2; exit 0
