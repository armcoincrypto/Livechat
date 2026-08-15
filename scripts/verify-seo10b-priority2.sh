#!/usr/bin/env bash
# SEO-10B — Priority 2 validation
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"
BASE_URL="${BASE_URL:-https://exswaping.com}"
FAIL=0
pass(){ echo "[seo10b] PASS $*"; }
fail(){ echo "[seo10b] FAIL $*"; FAIL=$((FAIL+1)); }
fetch_body(){ curl -sS --max-time 60 "$1" || true; }
word_count(){ python3 -c "import re,sys; t=re.sub(r'<[^>]+>',' ',sys.stdin.read()); print(len([w for w in re.split(r'\s+',t) if len(w)>2]))"; }

P2=(
  "6:kriptovaliuta-dlia-novickov-vvedenie-v-osnovy-kriptovaliut-blokcein-nft-i-defi:Trust:1500"
  "9:zakony-i-regulirovanie-obnovleniia-zakonodatelstva-o-kriptovaliute-v-raznyx-stranax:Trust:1500"
  "18:p2p-obmeny-v-rossii-pocemu-rastut-zaderzki-i-kak-exswaping-zashhishhaet-klientov:Trust:1500"
  "3:rukovodstvo-po-obmenu-kriptovaliuty-na-ukrainskie-karty-s-exswaping:Commercial:1500"
  "4:rukovodstvo-po-obmenu-kriptovaliuty-na-kriptovaliutu-s-exswaping:Commercial:1500"
  "5:exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena:Trust:1500"
  "20:exswaping-oficialno-dobavlen-v-monitoring-bestchange:News:800"
  "12:exswaping-teper-na-cryptoru:News:800"
)

for entry in "${P2[@]}"; do
  IFS=':' read -r id slug class min <<<"$entry"
  for loc in ru en; do
    url="${BASE_URL}/${loc}/blog/${slug}-${id}"
    body="$(fetch_body "$url")"
    st="$(curl -sSI --max-time 45 "$url" | awk 'NR==1{print $2; exit}')"
    [[ "$st" == "200" ]] && pass "$loc/$slug HTTP 200" || fail "$loc/$slug HTTP $st"
    grep -qi 'Error page\|<not-found' <<<"$body" && fail "$loc/$slug error page" || pass "$loc/$slug no error page"
    grep -qi 'robots" content="[^"]*noindex' <<<"$body" && fail "$loc/$slug noindex" || pass "$loc/$slug indexable"
    canon="https://exswaping.com/${loc}/blog/${slug}-${id}"
    grep -q "rel=\"canonical\" href=\"${canon}\"" <<<"$body" && pass "$loc/$slug canonical" || fail "$loc/$slug canonical"
    h1c="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
    [[ "$h1c" -eq 1 ]] && pass "$loc/$slug single H1" || fail "$loc/$slug H1=$h1c"
    grep -qi '"@type":"Article"' <<<"$body" && pass "$loc/$slug Article" || fail "$loc/$slug Article"
    grep -qi 'BreadcrumbList' <<<"$body" && pass "$loc/$slug Breadcrumb" || fail "$loc/$slug Breadcrumb"
    grep -qi 'FAQPage' <<<"$body" && pass "$loc/$slug FAQPage" || fail "$loc/$slug FAQPage"
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
[[ "$count" == "198" ]] && pass "sitemap 198" || fail "sitemap $count"
bash scripts/verify-blog-portfolio-seo.sh && pass "blog portfolio baseline" || fail "blog portfolio baseline"

if [[ "$FAIL" -gt 0 ]]; then echo "SEO10B_STATUS=FAIL failures=$FAIL" >&2; exit 2; fi
echo "SEO10B_STATUS=PASS" >&2; exit 0
