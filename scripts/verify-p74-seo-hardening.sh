#!/usr/bin/env bash
#
# P7.4 — SEO source hardening test suite
# Validates durable SEO behavior: sitemap, redirects, locale chains, indexability.
#
# Usage (from Laravel app root):
#   bash scripts/verify-p74-seo-hardening.sh
#
# Exit: 0 PASS | 2 FAIL
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE="${SEO_BASE_URL:-https://exswaping.com}"
FAIL=0

log() { echo "[p74-seo-hardening] $*" >&2; }
pass() { log "PASS $*"; }
fail() { log "FAIL $*"; FAIL=$((FAIL + 1)); }

run_existing() {
  local name="$1"
  shift
  log "RUN $name"
  if "$@"; then pass "$name"; else fail "$name"; fi
}

# --- Layer 1: existing guardrails ---
run_existing "verify-sitemap.sh" bash "$ROOT/scripts/verify-sitemap.sh" --no-generate
run_existing "verify-sitemap-command-tests" /usr/bin/php8.4 "$ROOT/scripts/verify-sitemap-command-tests.php"
run_existing "verify-exchange-indexability.sh" bash "$ROOT/scripts/verify-exchange-indexability.sh"
run_existing "verify-exchange-tier-policy.sh" bash "$ROOT/scripts/verify-exchange-tier-policy.sh"

# --- Layer 2: sitemap RU guides (P7.2B) ---
SITEMAP="${ROOT}/public/static/seo/sitemap.xml"
for slug in obmen-usdt-na-rubli monitoring-kriptovalyutnyh-obmennikov seti-usdt \
  bezopasnyj-kriptoobmen obmen-usdt-trc20 obmen-usdt-na-kartu; do
  if rg -q "/ru/guides/${slug}" "$SITEMAP" 2>/dev/null; then
    pass "sitemap contains /ru/guides/${slug}"
  else
    fail "sitemap missing /ru/guides/${slug}"
  fi
done
if rg -q "/ru/blog" "$SITEMAP" 2>/dev/null; then pass "sitemap contains /ru/blog (news hub)"; else fail "sitemap missing /ru/blog news hub"; fi

# --- Layer 3: P7.2B nginx redirects (single-hop 301) ---
check_redirect() {
  local path="$1" expect_fragment="$2"
  local out loc code
  out=$(curl -sI -A "P74Test/1.0" --max-time 20 "${BASE}${path}" | tr -d '\r')
  code=$(echo "$out" | awk '/^HTTP/{print $2; exit}')
  loc=$(echo "$out" | awk -F': ' '/^[Ll]ocation:/{print $2; exit}')
  if [[ "$code" == "301" && "$loc" == *"$expect_fragment"* ]]; then
    pass "301 ${path} -> ${expect_fragment}"
  else
    fail "301 ${path} expected fragment '${expect_fragment}' got code=${code} loc=${loc:-none}"
  fi
}

check_redirect "/ru/pages/contacts" "/ru/contacts"
check_redirect "/ru/pages/AMLKYC" "/ru/pages/amlkyc"
check_redirect "/ru/blog/exswaping-polnaia-instrukciia-20" "bestchange-20"
check_redirect "/uk/ka/partners" "/uk/partners"
check_redirect "/en/uk/uk/" "/en/"

# --- Layer 4: locale-chain must not 200 ---
for path in "/uk/ka/" "/en/uk/uk/blog/test"; do
  code=$(curl -sI -A "P74Test/1.0" --max-time 20 "${BASE}${path}" | awk '/^HTTP/{print $2; exit}')
  if [[ "$code" == "200" ]]; then fail "locale-chain ${path} returned 200"; else pass "locale-chain ${path} not 200 (${code})"; fi
done

# --- Layer 5: preservation markers present in dist ---
DIST="${DIST_ROOT:-/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server}"
SERVER_MJS="${DIST}/server.mjs"
CHUNK="${DIST}/ru/chunk-3RSX4ZSH.mjs"
for f in "$SERVER_MJS" "$CHUNK"; do
  if [[ -f "$f" ]]; then pass "dist file exists $(basename "$f")"; else fail "missing dist $(basename "$f")"; fi
done
if [[ -f "$CHUNK" ]] && rg -q "applyExsSeoV2" "$CHUNK" 2>/dev/null; then
  pass "chunk contains applyExsSeoV2"
else
  fail "chunk missing applyExsSeoV2 (preservation patch may be wiped)"
fi
if [[ -f "$SERVER_MJS" ]] && rg -q "EXS_SSR" "$SERVER_MJS" 2>/dev/null; then
  pass "server.mjs contains EXS_SSR markers"
else
  fail "server.mjs missing EXS_SSR markers"
fi

# --- Layer 6: post-vendor hook exists ---
HOOK="/var/www/exswaping_co_usr/data/www/exswaping.com/scripts/post-dist-update.sh"
PRESERV="/opt/exswaping-preservation/post-vendor-dist-seo.sh"
[[ -f "$HOOK" ]] && pass "post-dist-update.sh exists" || fail "post-dist-update.sh missing"
[[ -x "$PRESERV" ]] && pass "post-vendor-dist-seo.sh executable" || fail "post-vendor-dist-seo.sh missing"

if [[ "$FAIL" -gt 0 ]]; then
  log "RESULT FAIL ($FAIL failures)"
  exit 2
fi
log "RESULT PASS"
exit 0
