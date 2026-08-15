#!/usr/bin/env bash
#
# P-UI-MASTER-20 — Verify active production UI freeze (m19 final)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

CANONICAL="$ROOT/public/static/premium-ui-overrides.css"
FREEZE_DIR="/root/exswaping-nginx-backups/P-UI-MASTER-20-FREEZE"
CACHE_VERSION="20260627m19"
FAIL=0

pass() { echo "OK: $*"; }
fail() { echo "FAIL: $*" >&2; FAIL=1; }

echo "=== P-UI-MASTER-20 active freeze verification (m19 final) ==="

python3 - <<'PY' || fail "CSS brace balance"
import re
p="/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/premium-ui-overrides.css"
text=re.sub(r'/\*.*?\*/','',open(p).read(),flags=re.S)
assert text.count('{')==text.count('}'), 'brace mismatch'
print(f"braces OK ({text.count('{')})")
PY

if [[ -f "$FREEZE_DIR/SHA256SUMS.txt" ]]; then
  (cd "$FREEZE_DIR" && sha256sum -c SHA256SUMS.txt >/dev/null) && pass "freeze archive SHA256" || fail "freeze archive SHA256"
elif [[ -f "$FREEZE_DIR/SHA256SUMS.final.txt" ]]; then
  (cd "$FREEZE_DIR" && sha256sum -c SHA256SUMS.final.txt >/dev/null) && pass "freeze archive SHA256" || fail "freeze archive SHA256"
else
  fail "missing $FREEZE_DIR/SHA256SUMS.txt"
fi

[[ -f "$CANONICAL" ]] && pass "canonical CSS exists" || fail "canonical CSS missing"

nginx -t >/dev/null 2>&1 && pass "nginx -t" || fail "nginx -t"
grep -q "?v=$CACHE_VERSION" /etc/nginx/snippets/exswaping-premium-ui-overrides.conf && pass "nginx cache bust m19" || fail "nginx cache bust"

code=$(curl -s -o /dev/null -w '%{http_code}' "https://exswaping.com/static/premium-ui-overrides.css?v=$CACHE_VERSION")
[[ "$code" == "200" ]] && pass "static CSS HTTP 200" || fail "static CSS HTTP $code"

for loc in ru en; do
  css=$(curl -s "https://exswaping.com/${loc}/" | grep -o "premium-ui-overrides.css[^\"']*" | head -1)
  [[ "$css" == *"$CACHE_VERSION"* ]] && pass "$loc homepage CSS m19" || fail "$loc homepage CSS ($css)"
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://exswaping.com/${loc}/")
  [[ "$code" == "200" ]] && pass "$loc homepage HTTP 200" || fail "$loc homepage HTTP $code"
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://exswaping.com/${loc}/exchange/USDTTRC20/SBERRUB")
  [[ "$code" == "200" ]] && pass "$loc exchange HTTP 200" || fail "$loc exchange HTTP $code"
done

CANON_MD5=$(md5sum "$CANONICAL" | awk '{print $1}')
for f in /var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/browser/{assets,en,ru,uk,ka,zh}/assets/css/premium-ui-overrides.css; do
  if [[ -f "$f" ]]; then
    md5=$(md5sum "$f" | awk '{print $1}')
    [[ "$md5" == "$CANON_MD5" ]] && pass "mirror md5 $(basename $(dirname $(dirname $f)))" || fail "mirror mismatch $f"
  fi
done

if [[ -f "$ROOT/scripts/validate-homepage-exchange-links.php" ]]; then
  php8.4 "$ROOT/scripts/validate-homepage-exchange-links.php" >/dev/null && pass "footer SEO validator" || fail "footer SEO validator"
fi

ref_code=$(curl -s -o /dev/null -w '%{http_code}' 'https://exswaping.com/ru/?ref=MLyn')
[[ "$ref_code" == "200" ]] && pass "BestChange ref preserved" || fail "BestChange ref HTTP $ref_code"

if command -v pm2 >/dev/null 2>&1; then
  pm2 jlist 2>/dev/null | grep -q '"name":"iexexchanger".*"status":"online"' && pass "PM2 iexexchanger online" || fail "PM2 iexexchanger not online"
else
  echo "SKIP: pm2 not available"
fi

echo "=== Summary ==="
if [[ "$FAIL" -eq 0 ]]; then
  echo "PASS: P_UI_MASTER_20_M19_FINAL_FREEZE_CERTIFIED"
  exit 0
else
  echo "FAIL: active freeze verification — run ./scripts/premium-ui-restore-active.sh"
  exit 1
fi
