#!/usr/bin/env bash
#
# P-UI-MASTER-11 — Sync frozen m10 CSS to Angular dist mirrors
#
# Vendor iex dist updates overwrite dist/exchanger/browser/*/assets/css/.
# Run after every dist refresh (see post-dist-update.sh hook).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

CANONICAL="${PREMIUM_UI_CANONICAL:-$ROOT/public/static/premium-ui-overrides.css}"
DIST_ROOT="${PREMIUM_UI_DIST_ROOT:-/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/browser}"
CACHE_VERSION="${PREMIUM_UI_CACHE_VERSION:-20260627m16}"

[[ -f "$CANONICAL" ]] || { echo "[premium-ui-sync-m10] ERROR: missing $CANONICAL" >&2; exit 1; }

EXPECTED="premium-ui-overrides.css?v=$CACHE_VERSION"
if ! grep -q "$EXPECTED" /etc/nginx/snippets/exswaping-premium-ui-overrides.conf 2>/dev/null; then
  echo "[premium-ui-sync-m10] WARN: nginx snippet does not reference ?v=$CACHE_VERSION — run premium-ui-restore-m12.sh or premium-ui-restore-m10.sh"
fi

MIRRORS=(
  "$DIST_ROOT/assets/css/premium-ui-overrides.css"
  "$DIST_ROOT/en/assets/css/premium-ui-overrides.css"
  "$DIST_ROOT/ru/assets/css/premium-ui-overrides.css"
  "$DIST_ROOT/uk/assets/css/premium-ui-overrides.css"
  "$DIST_ROOT/ka/assets/css/premium-ui-overrides.css"
  "$DIST_ROOT/zh/assets/css/premium-ui-overrides.css"
)

for dest in "${MIRRORS[@]}"; do
  mkdir -p "$(dirname "$dest")"
  install -m 0644 "$CANONICAL" "$dest"
  echo "[premium-ui-sync-m10] synced -> $dest"
done

echo "[premium-ui-sync-m10] DONE ($(wc -c < "$CANONICAL") bytes)"
