#!/usr/bin/env bash
#
# SEO-1D — Sitemap monitoring & freshness guard (read-only checks + optional regenerate).
#
# Usage (from Laravel app root):
#   bash scripts/verify-sitemap.sh
#   bash scripts/verify-sitemap.sh --no-generate
#
# Environment (optional):
#   PHP_BIN      default /usr/bin/php8.4
#   PUBLIC_URL   default https://exswaping.com/static/seo/sitemap.xml
#   ROBOTS_URL   default https://exswaping.com/robots.txt
#
# Exit codes:
#   0 = PASS
#   1 = WARNING (degraded freshness, no hard failures)
#   2 = FAIL
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
PUBLIC_URL="${PUBLIC_URL:-https://exswaping.com/static/seo/sitemap.xml}"
ROBOTS_URL="${ROBOTS_URL:-https://exswaping.com/robots.txt}"
LOCAL_PATH="public/static/seo/sitemap.xml"
NO_GENERATE=0

if [[ "${1:-}" == "--no-generate" ]]; then
  NO_GENERATE=1
elif [[ -n "${1:-}" ]]; then
  echo "Unknown argument: $1" >&2
  echo "Usage: bash scripts/verify-sitemap.sh [--no-generate]" >&2
  exit 2
fi

MIN_LOC_COUNT=150
MAX_AGE_SECONDS=$((48 * 3600))
WARN_AGE_SECONDS=$((24 * 3600))

log() { echo "[sitemap-monitor] $*" >&2; }

FAIL_COUNT=0
WARN_COUNT=0

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

if [[ ! -f artisan ]] || [[ ! -f .env ]]; then
  echo "SITEMAP_MONITOR_STATUS=FAIL" >&2
  log "FAIL not in Laravel app root (missing artisan or .env)"
  exit 2
fi

if [[ ! -x "$PHP_BIN" ]] && ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "SITEMAP_MONITOR_STATUS=FAIL" >&2
  log "FAIL PHP not found: $PHP_BIN"
  exit 2
fi

# --- Local file presence / readability ---
if [[ ! -e "$LOCAL_PATH" ]]; then
  fail "local sitemap missing: $LOCAL_PATH"
elif [[ ! -f "$LOCAL_PATH" ]]; then
  fail "local sitemap path is not a regular file: $LOCAL_PATH"
elif [[ ! -r "$LOCAL_PATH" ]]; then
  fail "local sitemap is not readable: $LOCAL_PATH"
else
  pass "local sitemap exists and is readable"
fi

local_mtime=0
age_seconds=999999
generated_at="unknown"

if [[ -f "$LOCAL_PATH" ]] && [[ -r "$LOCAL_PATH" ]]; then
  local_mtime="$(stat -c %Y "$LOCAL_PATH" 2>/dev/null || echo 0)"
  now_epoch="$(date +%s)"
  age_seconds=$((now_epoch - local_mtime))
  generated_at="$(date -d "@${local_mtime}" -Iseconds 2>/dev/null || date -r "$local_mtime" -Iseconds 2>/dev/null || echo "unknown")"

  if [[ "$age_seconds" -ge "$MAX_AGE_SECONDS" ]]; then
    fail "local sitemap age ${age_seconds}s exceeds 48h limit"
  elif [[ "$age_seconds" -gt "$WARN_AGE_SECONDS" ]]; then
    warn "local sitemap age ${age_seconds}s exceeds 24h warning threshold (still within 48h)"
  else
    pass "local sitemap age ${age_seconds}s within freshness window"
  fi
fi

extract_locs() {
  sed -n 's/.*<loc>\([^<]*\)<\/loc>.*/\1/p' "$1" 2>/dev/null || true
}

local_loc_count=0
public_loc_count=0
query_url_count=0
duplicate_loc_count=0
locale_chain_count=0
foreign_host_count=0

if [[ -f "$LOCAL_PATH" ]] && [[ -r "$LOCAL_PATH" ]]; then
  mapfile -t local_locs < <(extract_locs "$LOCAL_PATH")
  local_loc_count="${#local_locs[@]}"

  if [[ "$local_loc_count" -le "$MIN_LOC_COUNT" ]]; then
    fail "local <loc> count ${local_loc_count} is not above ${MIN_LOC_COUNT}"
  else
    pass "local <loc> count ${local_loc_count} above ${MIN_LOC_COUNT}"
  fi

  query_url_count="$(printf '%s\n' "${local_locs[@]}" | grep -c '?' || true)"
  if [[ "$query_url_count" -gt 0 ]]; then
    fail "found ${query_url_count} query-string URL(s) in local sitemap"
  else
    pass "no query-string URLs in local sitemap"
  fi

  duplicate_loc_count="$(printf '%s\n' "${local_locs[@]}" | sort | uniq -d | wc -l | tr -d ' ')"
  if [[ "$duplicate_loc_count" -gt 0 ]]; then
    fail "found ${duplicate_loc_count} duplicate <loc> value(s) in local sitemap"
  else
    pass "no duplicate <loc> values in local sitemap"
  fi

  locale_chain_count="$(printf '%s\n' "${local_locs[@]}" | grep -Ec '/(ru|en|uk|ka|zh)/(ru|en|uk|ka|zh)/' || true)"
  if [[ "$locale_chain_count" -gt 0 ]]; then
    fail "found ${locale_chain_count} locale-chain URL(s) in local sitemap"
  else
    pass "no locale-chain URLs in local sitemap"
  fi

  foreign_host_count="$(printf '%s\n' "${local_locs[@]}" | grep -Eic 'localhost|app\.exswaping\.com' || true)"
  if [[ "$foreign_host_count" -gt 0 ]]; then
    fail "found ${foreign_host_count} localhost/app.exswaping.com URL(s) in local sitemap"
  else
    pass "no localhost/app.exswaping.com URLs in local sitemap"
  fi
fi

# --- Public sitemap ---
public_tmp="$(mktemp)"
trap 'rm -f "$public_tmp"' EXIT

public_fetch_ok=0
if curl -fsSL --max-time 30 "$PUBLIC_URL" -o "$public_tmp" 2>/dev/null; then
  public_fetch_ok=1
  pass "public sitemap fetched: $PUBLIC_URL"
else
  fail "unable to fetch public sitemap: $PUBLIC_URL"
fi

if [[ "$public_fetch_ok" -eq 1 ]]; then
  mapfile -t public_locs < <(extract_locs "$public_tmp")
  public_loc_count="${#public_locs[@]}"

  if [[ "$public_loc_count" -ne "$local_loc_count" ]]; then
    fail "public <loc> count ${public_loc_count} does not match local ${local_loc_count}"
  else
    pass "public <loc> count matches local (${public_loc_count})"
  fi
fi

# --- robots.txt sitemap reference ---
robots_sitemap_reference="missing"
robots_body="$(curl -fsSL --max-time 20 "$ROBOTS_URL" 2>/dev/null || true)"
if [[ -n "$robots_body" ]] && echo "$robots_body" | grep -qi 'sitemap:'; then
  robots_sitemap_reference="present"
  pass "robots.txt contains sitemap reference"
else
  fail "robots.txt missing sitemap reference ($ROBOTS_URL)"
fi

# --- Optional artisan regeneration ---
artisan_update_sitemap="SKIPPED"
regenerated=0
mtime_before="$local_mtime"

if [[ "$NO_GENERATE" -eq 1 ]]; then
  log "INFO --no-generate: skipping php artisan update:sitemap"
else
  if "$PHP_BIN" artisan update:sitemap --silent >/dev/null 2>&1; then
    artisan_update_sitemap="PASS"
    regenerated=1
    pass "php artisan update:sitemap --silent exited 0"

    if [[ -f "$LOCAL_PATH" ]]; then
      local_mtime_after="$(stat -c %Y "$LOCAL_PATH" 2>/dev/null || echo 0)"
      if [[ "$local_mtime_after" -lt "$mtime_before" ]]; then
        fail "sitemap mtime did not advance after generation (before=${mtime_before}, after=${local_mtime_after})"
      else
        pass "sitemap mtime updated after generation"
      fi
      local_mtime="$local_mtime_after"
      now_epoch="$(date +%s)"
      age_seconds=$((now_epoch - local_mtime))
      generated_at="$(date -d "@${local_mtime}" -Iseconds 2>/dev/null || echo "unknown")"

      mapfile -t local_locs < <(extract_locs "$LOCAL_PATH")
      local_loc_count="${#local_locs[@]}"
    fi
  else
    artisan_update_sitemap="FAIL"
    fail "php artisan update:sitemap --silent failed"
  fi
fi

# --- Machine-readable summary (stdout) ---
if [[ "$FAIL_COUNT" -gt 0 ]]; then
  monitor_status="FAIL"
  exit_code=2
elif [[ "$WARN_COUNT" -gt 0 ]]; then
  monitor_status="WARNING"
  exit_code=1
else
  monitor_status="PASS"
  exit_code=0
fi

cat <<EOF
SITEMAP_MONITOR_STATUS=${monitor_status}
generatedAt=${generated_at}
localPath=${LOCAL_PATH}
publicUrl=${PUBLIC_URL}
localLocCount=${local_loc_count}
publicLocCount=${public_loc_count}
ageSeconds=${age_seconds}
queryUrlCount=${query_url_count}
duplicateLocCount=${duplicate_loc_count}
localeChainCount=${locale_chain_count}
foreignHostCount=${foreign_host_count}
robotsSitemapReference=${robots_sitemap_reference}
artisanUpdateSitemap=${artisan_update_sitemap}
regenerated=${regenerated}
failCount=${FAIL_COUNT}
warnCount=${WARN_COUNT}
EOF

log "RESULT: ${monitor_status} (fail=${FAIL_COUNT}, warn=${WARN_COUNT})"
exit "$exit_code"
