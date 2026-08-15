#!/usr/bin/env bash
# Exchange Trust Flow — validate trust links on exchange pages
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
FAIL_COUNT=0

log() { echo "[exchange-trust] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_status() {
  curl -sSI --max-time 45 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

fetch_body() {
  curl -sS --max-time 60 "$1" || true
}

check_exchange() {
  local label="$1"
  local url="$2"
  local title="$3"
  shift 3
  local -a paths=("$@")
  local body
  body="$(fetch_body "$url")"
  local status
  status="$(fetch_status "$url")"

  if [[ "$status" == "200" ]]; then
    pass "${label} HTTP 200"
  else
    fail "${label} HTTP ${status} (expected 200)"
    return
  fi

  if grep -q 'id="seo-exchange-trust-links"' <<<"$body"; then
    pass "${label} trust block visible in SSR"
  else
    fail "${label} missing trust block in SSR"
  fi

  if grep -q "$title" <<<"$body"; then
    pass "${label} section title present"
  else
    fail "${label} missing section title: ${title}"
  fi

  for path in "${paths[@]}"; do
    if grep -q "href=\"${path}\"" <<<"$body"; then
      pass "${label} link present ${path}"
    else
      fail "${label} missing link ${path}"
    fi
    local target_status
    target_status="$(fetch_status "${BASE_URL}${path}")"
    if [[ "$target_status" == "200" ]]; then
      pass "${label} target OK ${path}"
    else
      fail "${label} broken target ${path} (HTTP ${target_status})"
    fi
  done

  if grep -q 'class="exchange__finally' <<<"$body"; then
    pass "${label} exchange form markup preserved"
  else
    fail "${label} exchange form markup missing (regression)"
  fi

  if grep -q 'exchange__from' <<<"$body" && grep -q 'exchange__to' <<<"$body"; then
    pass "${label} give/receive blocks preserved"
  else
    fail "${label} give/receive blocks missing (regression)"
  fi

  if grep -q 'exchange__rates' <<<"$body" || grep -q 'exchange-progress' <<<"$body"; then
    pass "${label} rate/timer UI preserved"
  else
    fail "${label} rate/timer UI missing (regression)"
  fi

  if grep -q '<exchange-reserves' <<<"$body"; then
    pass "${label} reserves section preserved"
  else
    fail "${label} reserves section missing (regression)"
  fi

  local trust_pos reserves_pos form_end
  trust_pos="$(grep -bo 'id="seo-exchange-trust-links"' <<<"$body" | head -1 | cut -d: -f1 || echo 0)"
  reserves_pos="$(grep -bo '<exchange-reserves' <<<"$body" | head -1 | cut -d: -f1 || echo 0)"
  form_end="$(grep -bo '</form></div>' <<<"$body" | head -1 | cut -d: -f1 || echo 0)"
  if [[ "$trust_pos" -gt 0 && "$form_end" -gt 0 && "$trust_pos" -gt "$form_end" ]]; then
    pass "${label} trust block below exchange form"
  else
    fail "${label} trust block placement wrong (form=${form_end}, trust=${trust_pos})"
  fi
  if [[ "$trust_pos" -gt 0 && "$reserves_pos" -gt 0 && "$trust_pos" -lt "$reserves_pos" ]]; then
    pass "${label} trust block above reserves section"
  else
    fail "${label} trust/reserves order wrong"
  fi
}

check_exchange "RU Tier A" "${BASE_URL}/ru/exchange/USDTTRC20/SBERRUB" "Полезная информация" \
  "/ru/guides/bezopasnyj-kriptoobmen" \
  "/ru/guides/monitoring-kriptovalyutnyh-obmennikov" \
  "/ru/guides/seti-usdt"

check_exchange "RU BTC pair" "${BASE_URL}/ru/exchange/BTC/SBERRUB" "Полезная информация" \
  "/ru/guides/bezopasnyj-kriptoobmen" \
  "/ru/guides/monitoring-kriptovalyutnyh-obmennikov" \
  "/ru/guides/seti-usdt"

check_exchange "EN exchange" "${BASE_URL}/en/exchange/USDTTRC20/BTC" "Helpful Information" \
  "/en/guides/usdt-exchange" \
  "/en/guides/usdt-trc20-exchange" \
  "/en/guides/usdt-to-bank-card"

# Non-exchange pages must not get exchange trust block
for url in "${BASE_URL}/ru/" "${BASE_URL}/ru/guides/obmen-usdt-trc20"; do
  body="$(fetch_body "$url")"
  if grep -q 'id="seo-exchange-trust-links"' <<<"$body"; then
    fail "trust block leaked to non-exchange page ${url}"
  else
    pass "no trust block on ${url}"
  fi
done

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "EXCHANGE_TRUST_LINKS_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT)"
  exit 2
fi

echo "EXCHANGE_TRUST_LINKS_STATUS=PASS" >&2
echo "EXCHANGE_TRUST_FLOW_COMPLETE" >&2
log "RESULT PASS"
exit 0
