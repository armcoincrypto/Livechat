#!/usr/bin/env bash
# Guide Directory — validate /ru/guides and /en/guides directory pages
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
FAIL_COUNT=0

log() { echo "[guide-directory] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

check_directory() {
  local loc="$1"
  local title_need="$2"
  local h1_need="$3"
  shift 3
  local -a paths=("$@")
  local url="${BASE_URL}/${loc}/guides"
  local body
  body="$(fetch_body "$url")"
  local status
  status="$(fetch_status "$url")"

  if [[ "$status" == "200" ]]; then
    pass "${loc} directory HTTP 200"
  else
    fail "${loc} directory HTTP ${status} (expected 200)"
    return
  fi

  if grep -q 'id="seo-guide-directory"' <<<"$body"; then
    pass "${loc} directory SSR block visible"
  else
    fail "${loc} missing seo-guide-directory in SSR"
  fi

  if grep -q "$h1_need" <<<"$body"; then
    pass "${loc} H1 visible in SSR"
  else
    fail "${loc} missing H1: ${h1_need}"
  fi

  if grep -qi "<title>${title_need}" <<<"$body" || grep -qi "<title>.*${title_need}" <<<"$body"; then
    pass "${loc} page title set"
  else
    fail "${loc} title missing expected: ${title_need}"
  fi

  local canonical
  canonical="$(grep -oi 'rel="canonical" href="[^"]*"' <<<"$body" | head -1 || true)"
  if [[ "$canonical" == *"exswaping.com/${loc}/guides\""* ]] || [[ "$canonical" == *"exswaping.com/${loc}/guides/\""* ]]; then
    pass "${loc} canonical self-reference OK"
  else
    fail "${loc} canonical unexpected: ${canonical:-none}"
  fi

  for path in "${paths[@]}"; do
    if grep -q "href=\"${path}\"" <<<"$body"; then
      pass "${loc} crawlable link ${path}"
    else
      fail "${loc} missing link ${path}"
    fi
    if [[ "$(fetch_status "${BASE_URL}${path}")" == "200" ]]; then
      pass "${loc} target OK ${path}"
    else
      fail "${loc} broken target ${path}"
    fi
  done

  if grep -qi 'application/ld+json' <<<"$body" && grep -qi 'FAQPage' <<<"$body"; then
    fail "${loc} unexpected new FAQPage schema on directory"
  else
    pass "${loc} no new FAQPage schema on directory"
  fi
}

check_directory "ru" "Руководства по обмену криптовалют" "Руководства по обмену криптовалют" \
  "/ru/guides/obmen-usdt-na-rubli" \
  "/ru/guides/obmen-usdt-trc20" \
  "/ru/guides/obmen-usdt-na-kartu" \
  "/ru/guides/usdt-trc20-i-erc20" \
  "/ru/guides/bezopasnyj-kriptoobmen" \
  "/ru/guides/monitoring-kriptovalyutnyh-obmennikov" \
  "/ru/guides/seti-usdt"

check_directory "en" "Cryptocurrency Exchange Guides" "Cryptocurrency Exchange Guides" \
  "/en/guides/usdt-exchange" \
  "/en/guides/usdt-trc20-exchange" \
  "/en/guides/usdt-to-bank-card"

# Child guide pages must not show directory index block
child_body="$(fetch_body "${BASE_URL}/ru/guides/obmen-usdt-trc20")"
if grep -q 'id="seo-guide-directory"' <<<"$child_body"; then
  fail "directory block leaked to child guide page"
else
  pass "child guide /ru/guides/obmen-usdt-trc20 has no directory block"
fi

child_canonical="$(grep -oi 'rel="canonical" href="[^"]*"' <<<"$child_body" | head -1 || true)"
if [[ "$child_canonical" == *"/ru/guides/obmen-usdt-trc20"* ]]; then
  pass "child guide canonical unchanged"
else
  fail "child guide canonical regression: ${child_canonical:-none}"
fi

if [[ -f "$SITEMAP_PATH" ]]; then
  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  if [[ "$count" == "200" ]]; then
    pass "sitemap count includes guide directories (200)"
  else
    fail "sitemap count ${count} (expected 200)"
  fi
  if grep -q 'https://exswaping.com/ru/guides</loc>' "$SITEMAP_PATH" && grep -q 'https://exswaping.com/en/guides</loc>' "$SITEMAP_PATH"; then
    pass "guide directories listed in sitemap"
  else
    fail "guide directory URLs missing from sitemap"
  fi
else
  fail "sitemap missing"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "GUIDE_DIRECTORY_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT)"
  exit 2
fi

echo "GUIDE_DIRECTORY_STATUS=PASS" >&2
echo "GUIDE_DIRECTORY_COMPLETE" >&2
log "RESULT PASS"
exit 0
