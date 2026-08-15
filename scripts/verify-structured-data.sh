#!/usr/bin/env bash
#
# SEO-6 — Structured data validation.
#
# Usage:
#   bash scripts/verify-structured-data.sh
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

log() { echo "[structured-data] $*" >&2; }
fail() { log "FAIL $*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
warn() { log "WARN $*"; WARN_COUNT=$((WARN_COUNT + 1)); }
pass() { log "PASS $*"; }

fetch_body() {
  curl -sS --max-time 45 "$1" || true
}

fetch_status() {
  curl -sSI --max-time 30 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
}

json_ld_blocks() {
  grep -oi '<script type="application/ld+json"[^>]*>[^<]*</script>' <<<"$1" || true
}

has_type() {
  grep -q "\"@type\":\"$2\"" <<<"$1"
}

has_forbidden() {
  grep -qiE '"@type":"(Product|LocalBusiness|AggregateRating)"|"aggregateRating"|"reviewRating"' <<<"$1"
}

extract_ld_json() {
  printf '%s' "$1" | python3 -c '
import json, re, sys
html = sys.stdin.read()
for m in re.finditer(r"<script[^>]*type=\"application/ld\+json\"[^>]*>([^<]+)</script>", html, re.I):
    try:
        print(json.dumps(json.loads(m.group(1)), ensure_ascii=False))
    except Exception:
        pass
'
}

check_page() {
  local label="$1" body="$2"
  local blocks combined
  blocks="$(json_ld_blocks "$body")"
  combined="$(printf '%s\n' "$blocks")"
  if [[ -z "$combined" ]]; then
    echo ""
    return
  fi
  extract_ld_json "$body"
}

# Homepage RU
RU_HOME="$(fetch_body "${BASE_URL}/ru/")"
RU_HOME_LD="$(check_page ru_home "$RU_HOME")"
if [[ -z "$RU_HOME_LD" ]]; then
  fail "RU homepage missing JSON-LD"
else
  if grep -q '"@type": "Organization"' <<<"$RU_HOME_LD" && grep -q '"@type": "WebSite"' <<<"$RU_HOME_LD"; then
    pass "RU homepage has Organization + WebSite"
  else
    fail "RU homepage missing Organization or WebSite"
  fi
  if grep -q '#organization' <<<"$RU_HOME_LD" && grep -q '#website' <<<"$RU_HOME_LD"; then
    pass "RU homepage schema has @id anchors"
  else
    warn "RU homepage schema missing @id anchors"
  fi
  if has_forbidden "$RU_HOME_LD"; then
    fail "RU homepage has forbidden schema types"
  fi
fi

# Homepage EN
EN_HOME="$(fetch_body "${BASE_URL}/en/")"
EN_HOME_LD="$(check_page en_home "$EN_HOME")"
if [[ -z "$EN_HOME_LD" ]]; then
  fail "EN homepage missing JSON-LD"
else
  if grep -q '"@type": "Organization"' <<<"$EN_HOME_LD" && grep -q '"@type": "WebSite"' <<<"$EN_HOME_LD"; then
    pass "EN homepage has Organization + WebSite"
  else
    fail "EN homepage missing Organization or WebSite"
  fi
  if has_forbidden "$EN_HOME_LD"; then
    fail "EN homepage has forbidden schema types"
  fi
fi

# Tier A RU exchange
EX_BODY="$(fetch_body "${BASE_URL}/ru/exchange/DASH/USDTTRC20")"
EX_LD="$(check_page exchange "$EX_BODY")"
if [[ -z "$EX_LD" ]]; then
  fail "Tier A RU exchange missing JSON-LD"
else
  if grep -q '"@type": "BreadcrumbList"' <<<"$EX_LD" && grep -q '"@type": "FinancialService"' <<<"$EX_LD"; then
    pass "Tier A RU exchange has BreadcrumbList + FinancialService"
  else
    fail "Tier A RU exchange missing BreadcrumbList or FinancialService"
  fi
  if grep -q '"@type": "Organization"' <<<"$EX_LD"; then
    warn "Tier A exchange still has Organization (redundant)"
  else
    pass "Tier A exchange has no redundant Organization"
  fi
  if has_forbidden "$EX_LD"; then
    fail "Tier A exchange has forbidden schema"
  fi
fi

# EN noindex exchange — no JSON-LD
EN_EX="$(fetch_body "${BASE_URL}/en/exchange/DASH/USDTTRC20")"
EN_EX_LD_COUNT="$(json_ld_blocks "$EN_EX" | wc -l | tr -d ' ')"
if [[ "$EN_EX_LD_COUNT" == "0" ]]; then
  pass "EN noindex exchange has no JSON-LD"
else
  fail "EN noindex exchange has JSON-LD (count=$EN_EX_LD_COUNT)"
fi
if grep -qi 'noindex' <<<"$EN_EX"; then
  pass "EN exchange still noindex"
else
  fail "EN exchange missing noindex"
fi

# Soft 404
SOFT_STATUS="$(fetch_status "${BASE_URL}/ru/xyzrandom404test")"
SOFT_STATUS="${SOFT_STATUS:-000}"
SOFT_BODY="$(fetch_body "${BASE_URL}/ru/xyzrandom404test")"
SOFT_LD_COUNT="$(json_ld_blocks "$SOFT_BODY" | wc -l | tr -d ' ')"
if [[ "$SOFT_STATUS" == "404" && "$SOFT_LD_COUNT" == "0" ]]; then
  pass "soft 404 has no JSON-LD"
else
  fail "soft 404 schema check failed (status=$SOFT_STATUS ld=$SOFT_LD_COUNT)"
fi

# FAQ RU
FAQ_BODY="$(fetch_body "${BASE_URL}/ru/faq")"
FAQ_LD="$(check_page faq "$FAQ_BODY")"
if grep -q '"@type": "FAQPage"' <<<"$FAQ_LD"; then
  pass "FAQ RU has FAQPage"
else
  fail "FAQ RU missing FAQPage"
fi
if grep -q '"@type": "Organization"' <<<"$FAQ_LD"; then
  warn "FAQ RU also has Organization @graph (redundant but non-blocking)"
else
  pass "FAQ RU has FAQPage only (no redundant Organization)"
fi

# Forbidden global scan on indexable samples
COMBINED="$RU_HOME_LD$EN_HOME_LD$EX_LD$FAQ_LD"
if echo "$COMBINED" | grep -qiE 'aggregateRating|reviewRating|"@type": "Product"|"@type": "LocalBusiness"'; then
  fail "forbidden schema detected in samples"
else
  pass "no fake Product/LocalBusiness/aggregateRating in samples"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "STRUCTURED_DATA_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "STRUCTURED_DATA_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "STRUCTURED_DATA_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
