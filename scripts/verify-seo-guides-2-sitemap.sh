#!/usr/bin/env bash
# SEO-GUIDES-2 — validate guide directory sitemap inclusion
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
PUBLIC_SITEMAP_URL="${PUBLIC_SITEMAP_URL:-https://exswaping.com/static/seo/sitemap.xml}"
FAIL_COUNT=0

log() { echo "[seo-guides-2] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

check_directory_sitemap() {
  local loc="$1"
  local url="${BASE_URL}/${loc}/guides"
  local body
  body="$(fetch_body "$url")"
  local status
  status="$(fetch_status "$url")"

  [[ "$status" == "200" ]] && pass "${loc}/guides HTTP 200" || fail "${loc}/guides HTTP ${status}"

  if grep -q 'id="seo-guide-directory"' <<<"$body"; then
    pass "${loc}/guides SSR directory block visible"
  else
    fail "${loc}/guides missing #seo-guide-directory"
  fi

  local canonical
  canonical="$(grep -oi 'rel="canonical" href="[^"]*"' <<<"$body" | head -1 || true)"
  if [[ "$canonical" == *"exswaping.com/${loc}/guides\""* ]]; then
    pass "${loc}/guides self canonical OK"
  else
    fail "${loc}/guides canonical unexpected: ${canonical:-none}"
  fi

  if grep -qi 'robots" content="[^"]*noindex' <<<"$body"; then
    fail "${loc}/guides has noindex"
  else
    pass "${loc}/guides no noindex"
  fi
}

if [[ ! -f "$SITEMAP_PATH" ]]; then
  fail "local sitemap missing: $SITEMAP_PATH"
else
  if xmllint --noout "$SITEMAP_PATH" 2>/dev/null; then
    pass "local sitemap XML valid"
  else
    fail "local sitemap XML invalid"
  fi

  count="$(grep -c '<url>' "$SITEMAP_PATH")"
  if [[ "$count" == "200" ]]; then
    pass "local sitemap URL count 200"
  else
    fail "local sitemap URL count ${count} (expected 200)"
  fi

  dupes="$(sed -n 's/.*<loc>\([^<]*\)<\/loc>.*/\1/p' "$SITEMAP_PATH" | sort | uniq -d | wc -l | tr -d ' ')"
  if [[ "$dupes" == "0" ]]; then
    pass "local sitemap no duplicate URLs"
  else
    fail "local sitemap duplicate URL count ${dupes}"
  fi

  for loc_url in \
    "https://exswaping.com/ru/guides" \
    "https://exswaping.com/en/guides"; do
    if grep -q "<loc>${loc_url}</loc>" "$SITEMAP_PATH"; then
      pass "local sitemap lists ${loc_url}"
    else
      fail "local sitemap missing ${loc_url}"
    fi
  done
fi

public_body="$(fetch_body "$PUBLIC_SITEMAP_URL")"
if [[ -n "$public_body" ]]; then
  if xmllint --noout - 2>/dev/null <<<"$public_body"; then
    pass "public sitemap XML valid"
  else
    fail "public sitemap XML invalid"
  fi

  public_count="$(grep -c '<url>' <<<"$public_body")"
  if [[ "$public_count" == "200" ]]; then
    pass "public sitemap URL count 200"
  else
    fail "public sitemap URL count ${public_count} (expected 200)"
  fi

  for loc_url in \
    "https://exswaping.com/ru/guides" \
    "https://exswaping.com/en/guides"; do
    if grep -q "<loc>${loc_url}</loc>" <<<"$public_body"; then
      pass "public sitemap lists ${loc_url}"
    else
      fail "public sitemap missing ${loc_url}"
    fi
  done
else
  fail "unable to fetch public sitemap"
fi

check_directory_sitemap "ru"
check_directory_sitemap "en"

for child in \
  "/ru/guides/obmen-usdt-trc20" \
  "/en/guides/usdt-exchange"; do
  child_status="$(fetch_status "${BASE_URL}${child}")"
  if [[ "$child_status" == "200" ]]; then
    pass "child guide still HTTP 200 ${child}"
  else
    fail "child guide HTTP ${child_status} ${child}"
  fi
done

if bash "$SCRIPT_DIR/verify-guide-directory.sh" >/tmp/verify-guide-directory-seo-guides-2.log 2>&1; then
  pass "verify-guide-directory.sh PASS"
else
  fail "verify-guide-directory.sh FAIL (see /tmp/verify-guide-directory-seo-guides-2.log)"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "SEO_GUIDES_2_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT)"
  exit 2
fi

echo "SEO_GUIDES_2_STATUS=PASS" >&2
echo "SEO_GUIDE_DIRECTORY_SITEMAP_COMPLETE" >&2
log "RESULT PASS"
exit 0
