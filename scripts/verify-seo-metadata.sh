#!/usr/bin/env bash
#
# SEO-5 — Metadata + H1 validation for indexable pages.
#
# Usage (from Laravel app root):
#   bash scripts/verify-seo-metadata.sh
#
# Exit codes:
#   0 = PASS
#   1 = WARNING
#   2 = FAIL
#
set -euo pipefail

BASE_URL="${BASE_URL:-https://exswaping.com}"
FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[seo-metadata] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_body() {
  curl -sS --max-time 45 "$1" || true
}

fetch_status() {
  curl -sSI --max-time 30 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

extract_title() {
  grep -oiE '<title>[^<]+' <<<"$1" | head -1 | sed 's/<title>//i'
}

extract_description() {
  grep -oiE '<meta name="description" content="[^"]+' <<<"$1" | head -1 | sed 's/.*content="//i'
}

count_h1() {
  grep -oi '<h1' <<<"$1" | wc -l | tr -d ' '
}

has_noindex() {
  grep -qi 'name="robots"[^>]*content="[^"]*noindex' <<<"$1"
}

has_canonical() {
  grep -qi 'rel="canonical"' <<<"$1"
}

has_hreflang() {
  grep -qi 'hreflang=' <<<"$1"
}

check_homepage() {
  local loc="$1" url="$2" want_title="$3" want_desc_fragment="$4" want_h1="$5"
  local body title desc h1c
  body="$(fetch_body "$url")"
  title="$(extract_title "$body")"
  desc="$(extract_description "$body")"
  h1c="$(count_h1 "$body")"

  if [[ -z "$title" ]]; then
    fail "$loc homepage missing title"
  elif [[ "$title" == *"$want_title"* ]]; then
    pass "$loc homepage title OK"
  else
    fail "$loc homepage title mismatch: $title"
  fi

  if [[ -z "$desc" ]]; then
    fail "$loc homepage missing description"
  elif [[ "$desc" == *"$want_desc_fragment"* ]]; then
    pass "$loc homepage description OK"
  else
    fail "$loc homepage description mismatch"
  fi

  if [[ "$h1c" == "1" ]] && grep -qi "$want_h1" <<<"$body"; then
    pass "$loc homepage exactly one H1"
  else
    fail "$loc homepage H1 expected 1 with '$want_h1', got count=$h1c"
  fi
}

check_homepage "RU" "${BASE_URL}/ru/" \
  "Крипто обменник Exswaping — обмен USDT, BTC, ETH онлайн" \
  "онлайн крипто обменник" \
  "Крипто обменник Exswaping"

check_homepage "EN" "${BASE_URL}/en/" \
  "Exswaping Crypto Exchange — Swap USDT, BTC, ETH Online" \
  "online crypto exchange" \
  "Exswaping Crypto Exchange"

# Tier A RU exchange
EX_BODY="$(fetch_body "${BASE_URL}/ru/exchange/DASH/USDTTRC20")"
EX_TITLE="$(extract_title "$EX_BODY")"
EX_DESC="$(extract_description "$EX_BODY")"
EX_H1="$(count_h1 "$EX_BODY")"

if has_noindex "$EX_BODY"; then
  fail "Tier A RU exchange is noindexed"
else
  pass "Tier A RU exchange indexable (no noindex)"
fi

if [[ "$EX_TITLE" == *"Обмен"* && "$EX_TITLE" == *"Exswaping"* ]]; then
  pass "Tier A RU exchange title template OK: $EX_TITLE"
else
  fail "Tier A RU exchange title mismatch: $EX_TITLE"
fi

if [[ "$EX_DESC" == *"Безопасный обмен"* && "$EX_DESC" == *"Exswaping"* ]]; then
  pass "Tier A RU exchange description template OK"
else
  fail "Tier A RU exchange description mismatch"
fi

if [[ "$EX_H1" == "1" ]] && grep -qi 'Обмен' <<<"$EX_BODY"; then
  pass "Tier A RU exchange exactly one H1"
else
  fail "Tier A RU exchange H1 count=$EX_H1"
fi

if has_canonical "$EX_BODY"; then
  pass "Tier A RU exchange has canonical"
else
  fail "Tier A RU exchange missing canonical"
fi

# EN exchange noindex preserved
EN_BODY="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
if has_noindex "$EN_BODY"; then
  pass "EN exchange remains noindex,follow"
else
  fail "EN exchange missing noindex"
fi

# Soft 404
SOFT_STATUS="$(fetch_status "${BASE_URL}/ru/xyzrandom404test")"
SOFT_STATUS="${SOFT_STATUS:-000}"
SOFT_BODY="$(fetch_body "${BASE_URL}/ru/xyzrandom404test")"
if [[ "$SOFT_STATUS" == "404" ]]; then
  pass "soft 404 returns 404"
else
  fail "soft 404 expected 404, got $SOFT_STATUS"
fi
if has_canonical "$SOFT_BODY"; then
  fail "soft 404 has canonical"
else
  pass "soft 404 no canonical"
fi
if has_hreflang "$SOFT_BODY"; then
  fail "soft 404 has hreflang"
else
  pass "soft 404 no hreflang"
fi
if has_noindex "$SOFT_BODY"; then
  pass "soft 404 noindex present"
else
  warn "soft 404 noindex not detected in HTML grep"
fi

# FAQ
RU_FAQ_BODY="$(fetch_body "${BASE_URL}/ru/faq")"
RU_FAQ_TITLE="$(extract_title "$RU_FAQ_BODY")"
if [[ "$RU_FAQ_TITLE" == *"Вопросы и ответы — Exswaping"* ]]; then
  pass "FAQ RU title OK"
else
  fail "FAQ RU title mismatch: $RU_FAQ_TITLE"
fi
if [[ "$(count_h1 "$RU_FAQ_BODY")" == "1" ]]; then
  pass "FAQ RU exactly one H1"
else
  fail "FAQ RU H1 count != 1"
fi

EN_FAQ_BODY="$(fetch_body "${BASE_URL}/en/faq")"
EN_FAQ_TITLE="$(extract_title "$EN_FAQ_BODY")"
if [[ "$EN_FAQ_TITLE" == *"Frequently Asked Questions — Exswaping"* ]]; then
  pass "FAQ EN title OK"
else
  fail "FAQ EN title mismatch: $EN_FAQ_TITLE"
fi
if [[ "$(count_h1 "$EN_FAQ_BODY")" == "1" ]]; then
  pass "FAQ EN exactly one H1"
else
  fail "FAQ EN H1 count != 1"
fi

# RU/EN hreflang on FAQ
if has_hreflang "$RU_FAQ_BODY"; then
  pass "FAQ RU hreflang preserved"
else
  warn "FAQ RU hreflang not detected"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "SEO_METADATA_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "SEO_METADATA_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "SEO_METADATA_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
