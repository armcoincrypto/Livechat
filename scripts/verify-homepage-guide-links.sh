#!/usr/bin/env bash
# Homepage Authority Flow — validate guide link section on RU/EN homepages
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
FAIL_COUNT=0

log() { echo "[homepage-guides] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

check_homepage() {
  local loc="$1"
  local title="$2"
  shift 2
  local -a paths=("$@")
  local url="${BASE_URL}/${loc}/"
  local body
  body="$(fetch_body "$url")"
  local status
  status="$(fetch_status "$url")"

  if [[ "$status" == "200" ]]; then
    pass "${loc} homepage HTTP 200"
  else
    fail "${loc} homepage HTTP ${status} (expected 200)"
    return
  fi

  if grep -q 'id="seo-homepage-guide-links"' <<<"$body"; then
    pass "${loc} SSR contains guide section id"
  else
    fail "${loc} missing guide section id in SSR HTML"
  fi

  if grep -q "$title" <<<"$body"; then
    pass "${loc} SSR contains section title"
  else
    fail "${loc} missing section title: ${title}"
  fi

  for path in "${paths[@]}"; do
    if grep -q "href=\"${path}\"" <<<"$body"; then
      pass "${loc} link present ${path}"
    else
      fail "${loc} missing link ${path}"
    fi
    local guide_status
    guide_status="$(fetch_status "${BASE_URL}${path}")"
    if [[ "$guide_status" == "200" ]]; then
      pass "${loc} target OK ${path} (HTTP 200)"
    else
      fail "${loc} broken target ${path} (HTTP ${guide_status})"
    fi
  done

  local canonical
  canonical="$(grep -oi 'rel="canonical" href="[^"]*"' <<<"$body" | head -1 || true)"
  if [[ "$canonical" == *"exswaping.com/${loc}/"* ]] || [[ "$canonical" == *"exswaping.com/${loc}\""* ]]; then
    pass "${loc} canonical unchanged (self-reference present)"
  else
    fail "${loc} canonical missing or unexpected: ${canonical:-none}"
  fi

  if grep -qi 'application/ld+json' <<<"$body"; then
    pass "${loc} homepage schema present"
  else
    fail "${loc} homepage schema missing"
  fi

  if grep -q '"@type":"Organization"' <<<"$body" && grep -q '"@type":"WebSite"' <<<"$body"; then
    pass "${loc} Organization + WebSite schema unchanged"
  else
    fail "${loc} Organization/WebSite schema altered"
  fi

  if grep -q 'id="seo-popular-exchange-directions"' <<<"$body"; then
    pass "${loc} existing exchange footer section preserved"
  else
    fail "${loc} exchange footer section missing (regression)"
  fi

  local guide_pos exchange_pos
  guide_pos="$(grep -bo 'id="seo-homepage-guide-links"' <<<"$body" | head -1 | cut -d: -f1 || echo 0)"
  exchange_pos="$(grep -bo 'id="seo-popular-exchange-directions"' <<<"$body" | head -1 | cut -d: -f1 || echo 0)"
  if [[ "$guide_pos" -gt 0 && "$exchange_pos" -gt 0 && "$guide_pos" -lt "$exchange_pos" ]]; then
    pass "${loc} guide section above exchange footer"
  else
    fail "${loc} section order wrong (guide=${guide_pos}, exchange=${exchange_pos})"
  fi
}

check_homepage "ru" "Полезные руководства по обмену криптовалют" \
  "/ru/guides/obmen-usdt-na-rubli" \
  "/ru/guides/obmen-usdt-trc20" \
  "/ru/guides/obmen-usdt-na-kartu" \
  "/ru/guides/bezopasnyj-kriptoobmen" \
  "/ru/guides/monitoring-kriptovalyutnyh-obmennikov" \
  "/ru/guides/seti-usdt"

check_homepage "en" "Helpful Cryptocurrency Exchange Guides" \
  "/en/guides/usdt-exchange" \
  "/en/guides/usdt-trc20-exchange" \
  "/en/guides/usdt-to-bank-card"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "HOMEPAGE_GUIDE_LINKS_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT)"
  exit 2
fi

echo "HOMEPAGE_GUIDE_LINKS_STATUS=PASS" >&2
echo "HOMEPAGE_AUTHORITY_FLOW_COMPLETE" >&2
log "RESULT PASS"
exit 0
