#!/usr/bin/env bash
# SEO-POLISH-1 — Production-safe polish validation suite
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[seo-polish-1] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

run_check() {
  local name="$1"
  shift
  local exit_code=0
  log "RUN $name"
  "$@" || exit_code=$?
  case "$exit_code" in
    0) log "OK $name" ;;
    1)
      if [[ "$name" == "verify-full-site-visibility.sh" ]]; then
        WARN_COUNT=$((WARN_COUNT + 1))
        log "WARN $name (known legacy sitemap warnings allowed)"
      else
        FAIL_COUNT=$((FAIL_COUNT + 1))
        log "FAIL $name (exit 1)"
      fi
      ;;
    *)
      FAIL_COUNT=$((FAIL_COUNT + 1))
      log "FAIL $name (exit $exit_code)"
      ;;
  esac
}

# --- Existing validator suite ---
run_check "verify-sitemap.sh" bash scripts/verify-sitemap.sh --no-generate
run_check "verify-blog-portfolio-seo.sh" bash scripts/verify-blog-portfolio-seo.sh
run_check "verify-authority-news-seo.sh" bash scripts/verify-authority-news-seo.sh
run_check "verify-blog-recovery.sh" bash scripts/verify-blog-recovery.sh
run_check "verify-guide-usdt-trc20-seo.sh" bash scripts/verify-guide-usdt-trc20-seo.sh
run_check "verify-guide-usdt-na-kartu-seo.sh" bash scripts/verify-guide-usdt-na-kartu-seo.sh
run_check "verify-ru-seti-usdt-guide.sh" bash scripts/verify-ru-seti-usdt-guide.sh
run_check "verify-ru-monitoring-kriptovalyutnyh-obmennikov-guide.sh" bash scripts/verify-ru-monitoring-kriptovalyutnyh-obmennikov-guide.sh
run_check "verify-ru-bezopasnyj-kriptoobmen-guide.sh" bash scripts/verify-ru-bezopasnyj-kriptoobmen-guide.sh
run_check "verify-full-site-visibility.sh" bash scripts/verify-full-site-visibility.sh

# --- SEO-POLISH-1 targeted checks ---
log "RUN polish-specific checks"

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  if [[ "$count" == "198" ]]; then pass "sitemap count unchanged (198)"; else fail "sitemap count $count (expected 198)"; fi
else
  fail "sitemap missing at $SITEMAP_PATH"
fi

POLISH_URLS=(
  "${BASE_URL}/ru/contacts"
  "${BASE_URL}/en/contacts"
  "${BASE_URL}/ru/guides/obmen-usdt-trc20"
  "${BASE_URL}/ru/guides/obmen-usdt-na-kartu"
  "${BASE_URL}/ru/guides/seti-usdt"
  "${BASE_URL}/ru/pages/service"
  "${BASE_URL}/ru/pages/about"
)

for url in "${POLISH_URLS[@]}"; do
  st="$(fetch_status "$url")"
  if [[ "$st" == "200" ]]; then pass "HTTP 200 $url"; else fail "HTTP $st $url"; fi
done

for path in "/ru/contacts" "/en/contacts"; do
  body="$(fetch_body "${BASE_URL}${path}")"
  h1_count="$(grep -oi '<h1' <<<"$body" | wc -l | tr -d ' ')"
  if [[ "$h1_count" == "1" ]]; then pass "single H1 ${path}"; else fail "H1 count ${h1_count} on ${path}"; fi
  if grep -qi 'rel="canonical"' <<<"$body"; then pass "canonical present ${path}"; else fail "canonical missing ${path}"; fi
  if grep -qi 'application/ld+json' <<<"$body"; then pass "schema present ${path}"; else warn "schema not detected ${path}"; fi
  if grep -q 'iexexchanger-state' <<<"$body" || grep -qi '<h1' <<<"$body"; then pass "SSR content ${path}"; else fail "SSR shell only ${path}"; fi
done

LEGACY_HREF_PAGES=(
  "${BASE_URL}/ru/guides/obmen-usdt-trc20"
  "${BASE_URL}/ru/guides/obmen-usdt-na-kartu"
  "${BASE_URL}/ru/guides/seti-usdt"
  "${BASE_URL}/ru/guides/monitoring-kriptovalyutnyh-obmennikov"
  "${BASE_URL}/ru/guides/bezopasnyj-kriptoobmen"
  "${BASE_URL}/ru/faq"
  "${BASE_URL}/ru/"
)

for url in "${LEGACY_HREF_PAGES[@]}"; do
  body="$(fetch_body "$url")"
  if grep -q 'href="/ru/exchange-rules"' <<<"$body" || grep -q "href='/ru/exchange-rules'" <<<"$body"; then
    fail "legacy href /ru/exchange-rules on $url"
  else
    pass "no legacy /ru/exchange-rules href on $url"
  fi
  if grep -q 'href="/ru/about"' <<<"$body" || grep -q "href='/ru/about'" <<<"$body"; then
    fail "legacy href /ru/about on $url"
  else
    pass "no legacy /ru/about href on $url"
  fi
done

if [[ "$(fetch_status "${BASE_URL}/ru/exchange-rules")" == "404" ]]; then pass "legacy /ru/exchange-rules still 404 (no redirect added)"; else fail "legacy /ru/exchange-rules status changed"; fi
if [[ "$(fetch_status "${BASE_URL}/ru/about")" == "404" ]]; then pass "legacy /ru/about still 404 (no redirect added)"; else fail "legacy /ru/about status changed"; fi
if [[ "$(fetch_status "${BASE_URL}/ru/xyzrandom404test")" == "404" ]]; then pass "soft 404 guard unchanged"; else fail "soft 404 guard broken"; fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "SEO_POLISH_1_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

echo "SEO_POLISH_1_STATUS=PASS" >&2
echo "SEO_POLISH_1_COMPLETE" >&2
log "RESULT PASS (warnings=$WARN_COUNT)"
exit 0
