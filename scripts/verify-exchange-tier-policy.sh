#!/usr/bin/env bash
#
# SEO-4 — Exchange tier policy / sitemap truth verification.
#
# Usage (from Laravel app root):
#   bash scripts/verify-exchange-tier-policy.sh
#
# Environment (optional):
#   PHP_BIN              default /usr/bin/php8.4
#   BASE_URL             default https://exswaping.com
#   TIER_IDS_PATH        default storage/app/seo/exchange_index_tier_ids.txt
#   SITEMAP_PATH         default public/static/seo/sitemap.xml
#   NON_SITEMAP_RU_PAIR  default ADA/BRBKZT
#
# Exit codes:
#   0 = PASS
#   1 = WARNING
#   2 = FAIL
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
BASE_URL="${BASE_URL:-https://exswaping.com}"
TIER_IDS_PATH="${TIER_IDS_PATH:-storage/app/seo/exchange_index_tier_ids.txt}"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
NON_SITEMAP_RU_PAIR="${NON_SITEMAP_RU_PAIR:-ADA/BRBKZT}"

FAIL_COUNT=0
WARN_COUNT=0

log() { echo "[exchange-tier-policy] $*" >&2; }

fail() {
  log "FAIL $*"
  FAIL_COUNT=$((FAIL_COUNT + 1))
}

warn() {
  log "WARN $*"
  WARN_COUNT=$((WARN_COUNT + 1))
}

pass() {
  log "PASS $*"
}

fetch_body() {
  curl -sS --max-time 45 "$1" || true
}

fetch_status() {
  curl -sSI --max-time 30 "$1" 2>/dev/null | awk 'NR==1{print $2; exit}'
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

if [[ ! -f "$TIER_IDS_PATH" ]]; then
  echo "EXCHANGE_TIER_POLICY_STATUS=FAIL" >&2
  log "FAIL tier IDs file missing: $TIER_IDS_PATH"
  exit 2
fi

if [[ ! -f "$SITEMAP_PATH" ]]; then
  echo "EXCHANGE_TIER_POLICY_STATUS=FAIL" >&2
  log "FAIL sitemap missing: $SITEMAP_PATH"
  exit 2
fi

TIER_RAW_COUNT="$(grep -cve '^\s*$' "$TIER_IDS_PATH" || true)"
TIER_UNIQUE_COUNT="$(sort -u "$TIER_IDS_PATH" | grep -cve '^\s*$' || true)"
pass "tier file lines (non-empty): $TIER_RAW_COUNT"
pass "tier file unique IDs: $TIER_UNIQUE_COUNT"

if [[ "$TIER_RAW_COUNT" != "$TIER_UNIQUE_COUNT" ]]; then
  fail "duplicate tier IDs in $TIER_IDS_PATH ($TIER_RAW_COUNT raw vs $TIER_UNIQUE_COUNT unique)"
else
  pass "no duplicate tier IDs in source file"
fi

SITEMAP_TOTAL="$(grep -c '<loc>' "$SITEMAP_PATH" || true)"
SITEMAP_EXCHANGE_COUNT="$(grep -oE 'https://exswaping.com/[^<]+/exchange/[^<]+' "$SITEMAP_PATH" | sort -u | wc -l | tr -d ' ')"
pass "sitemap total URLs: $SITEMAP_TOTAL"
pass "sitemap unique exchange URLs: $SITEMAP_EXCHANGE_COUNT"

if [[ ! -x "$PHP_BIN" ]] && ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  warn "PHP not available for DB reconciliation — skipping tier/sitemap DB checks"
else
  export TIER_IDS_PATH
  RECON="$("$PHP_BIN" -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = getenv("TIER_IDS_PATH") ?: "storage/app/seo/exchange_index_tier_ids.txt";
$ids = [];
foreach (file($path) as $line) {
    $line = trim($line);
    if ($line === "" || str_starts_with($line, "#")) continue;
    $ids[] = (int) preg_split("/\s+/", $line)[0];
}
$ids = array_values(array_unique($ids));

$rows = App\Models\DirectionExchange::query()
    ->whereIn("id", $ids)
    ->with(["currency1:id,designation_xml", "currency2:id,designation_xml"])
    ->select(["id", "id_currency1", "id_currency2", "status"])
    ->orderBy("id")
    ->get();

$missing = array_values(array_diff($ids, $rows->pluck("id")->all()));
$inactive = [];
$active_urls = [];
$dupes = 0;
$seen = [];
foreach ($rows as $item) {
    if ((int) $item->status !== 1) {
        $inactive[] = $item->id;
        continue;
    }
    $from = trim((string) ($item->currency1?->designation_xml ?? ""));
    $to = trim((string) ($item->currency2?->designation_xml ?? ""));
    if ($from === "" || $to === "") continue;
    $url = "/ru/exchange/$from/$to";
    if (isset($seen[$url])) {
        $dupes++;
    } else {
        $seen[$url] = $item->id;
        $active_urls[] = $url;
    }
}

echo "tier_ids=" . count($ids) . "\n";
echo "missing_db=" . count($missing) . "\n";
echo "inactive=" . count($inactive) . "\n";
echo "active_unique_urls=" . count($active_urls) . "\n";
echo "active_dupes=" . $dupes . "\n";
if ($missing) echo "missing_ids=" . implode(",", $missing) . "\n";
if ($inactive) echo "inactive_ids=" . implode(",", $inactive) . "\n";
' 2>/dev/null || true)"

  if [[ -z "$RECON" ]]; then
    warn "DB reconciliation script failed"
  else
    while IFS= read -r line; do
      log "INFO $line"
    done <<<"$RECON"

    TIER_IDS="$(echo "$RECON" | awk -F= '/^tier_ids=/{print $2}')"
    ACTIVE_UNIQUE="$(echo "$RECON" | awk -F= '/^active_unique_urls=/{print $2}')"
    INACTIVE_COUNT="$(echo "$RECON" | awk -F= '/^inactive=/{print $2}')"
    ACTIVE_DUPES="$(echo "$RECON" | awk -F= '/^active_dupes=/{print $2}')"
    MISSING_DB="$(echo "$RECON" | awk -F= '/^missing_db=/{print $2}')"

    if [[ "${MISSING_DB:-0}" != "0" ]]; then
      fail "tier IDs missing from database: $(echo "$RECON" | awk -F= '/^missing_ids=/{print $2}')"
    else
      pass "all tier IDs exist in database"
    fi

    if [[ "${INACTIVE_COUNT:-0}" != "0" ]]; then
      fail "inactive tier IDs present: $(echo "$RECON" | awk -F= '/^inactive_ids=/{print $2}')"
    else
      pass "no inactive tier IDs in source"
    fi

    if [[ "${ACTIVE_DUPES:-0}" != "0" ]]; then
      fail "duplicate active tier IDs resolve to same exchange URL (dupes=$ACTIVE_DUPES)"
    else
      pass "no duplicate active tier URL collisions"
    fi

    if [[ -n "${ACTIVE_UNIQUE:-}" && -n "${SITEMAP_EXCHANGE_COUNT:-}" && "$ACTIVE_UNIQUE" != "$SITEMAP_EXCHANGE_COUNT" ]]; then
      fail "active unique tier URLs ($ACTIVE_UNIQUE) != sitemap exchange URLs ($SITEMAP_EXCHANGE_COUNT)"
    else
      pass "active tier unique URLs match sitemap exchange count ($SITEMAP_EXCHANGE_COUNT)"
    fi

    if [[ -n "${TIER_IDS:-}" && -n "${SITEMAP_EXCHANGE_COUNT:-}" && "$TIER_IDS" != "$SITEMAP_EXCHANGE_COUNT" ]]; then
      warn "tier ID count ($TIER_IDS) != sitemap exchange URL count ($SITEMAP_EXCHANGE_COUNT) — acceptable only if explained by URL dedupe"
    else
      pass "tier ID count equals sitemap exchange URL count ($TIER_IDS)"
    fi
  fi
fi

SITEMAP_PAIR="$(grep -oE 'https://exswaping.com/ru/exchange/[^<]+' "$SITEMAP_PATH" | head -1 || true)"
if [[ -z "$SITEMAP_PAIR" ]]; then
  fail "no sitemap exchange sample found"
else
  BODY="$(fetch_body "$SITEMAP_PAIR")"
  if has_noindex "$BODY"; then
    fail "sitemap exchange is noindexed: $SITEMAP_PAIR"
  else
    pass "sitemap exchange indexable: $SITEMAP_PAIR"
  fi
  if ! has_canonical "$BODY"; then
    fail "sitemap exchange missing canonical"
  else
    pass "sitemap exchange has canonical"
  fi
  if has_hreflang "$BODY"; then
    fail "sitemap exchange emits hreflang"
  else
    pass "sitemap exchange has no hreflang"
  fi
fi

EN_URL="${BASE_URL}/en/exchange/DASH/USDTTRC20"
EN_BODY="$(fetch_body "$EN_URL")"
if has_noindex "$EN_BODY"; then
  pass "EN exchange noindex,follow"
else
  fail "EN exchange missing noindex"
fi

NON_SITEMAP_URL="${BASE_URL}/ru/exchange/${NON_SITEMAP_RU_PAIR}"
NON_STATUS="$(fetch_status "$NON_SITEMAP_URL")"
NON_STATUS="${NON_STATUS:-000}"
if [[ "$NON_STATUS" == "200" ]]; then
  NON_BODY="$(fetch_body "$NON_SITEMAP_URL")"
  if has_noindex "$NON_BODY"; then
    pass "non-sitemap RU exchange noindex,follow: $NON_SITEMAP_URL"
  else
    fail "non-sitemap RU exchange missing noindex: $NON_SITEMAP_URL"
  fi
else
  warn "non-sitemap RU sample not HTTP 200 ($NON_STATUS): $NON_SITEMAP_URL"
fi

INVALID_URL="${BASE_URL}/ru/exchange/INVALID/PAIR"
INVALID_STATUS="$(fetch_status "$INVALID_URL")"
INVALID_STATUS="${INVALID_STATUS:-000}"
if [[ "$INVALID_STATUS" == "404" ]]; then
  pass "invalid exchange returns 404"
else
  fail "invalid exchange expected 404, got $INVALID_STATUS"
fi

VALID_PAIRS="$(grep -cE '^"[^"]+:[^"]+" 1;' storage/app/seo/valid_exchange_pairs.nginx.map 2>/dev/null || echo 0)"
CONTROLLED_NOINDEX=$((VALID_PAIRS * 4 - SITEMAP_EXCHANGE_COUNT))
log "INFO valid_exchange_pairs=$VALID_PAIRS controlled_noindex_approx=$CONTROLLED_NOINDEX"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  echo "EXCHANGE_TIER_POLICY_STATUS=FAIL" >&2
  log "RESULT FAIL (failures=$FAIL_COUNT warnings=$WARN_COUNT)"
  exit 2
fi

if [[ "$WARN_COUNT" -gt 0 ]]; then
  echo "EXCHANGE_TIER_POLICY_STATUS=WARNING" >&2
  log "RESULT WARNING (warnings=$WARN_COUNT)"
  exit 1
fi

echo "EXCHANGE_TIER_POLICY_STATUS=PASS" >&2
log "RESULT PASS"
exit 0
