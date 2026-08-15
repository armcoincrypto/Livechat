#!/usr/bin/env bash
#
# P-UI-MASTER-11 — Restore frozen premium UI baseline (m10)
#
# Restores canonical CSS, nginx sub_filter snippet, dist mirrors, and reloads nginx.
# Use after accidental overwrite, vendor dist refresh, or rollback from bad CSS edit.
#
# Usage:
#   ./scripts/premium-ui-restore-m10.sh
#   RELOAD_NGINX=0 ./scripts/premium-ui-restore-m10.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

FREEZE_DIR="${PREMIUM_UI_FREEZE_DIR:-/root/exswaping-nginx-backups/P-UI-MASTER-11-FREEZE}"
CANONICAL="${PREMIUM_UI_CANONICAL:-$ROOT/public/static/premium-ui-overrides.css}"
NGINX_SNIPPET="${PREMIUM_UI_NGINX_SNIPPET:-/etc/nginx/snippets/exswaping-premium-ui-overrides.conf}"
DIST_ROOT="${PREMIUM_UI_DIST_ROOT:-/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/browser}"
CACHE_VERSION="${PREMIUM_UI_CACHE_VERSION:-20260627m10}"

CSS_ARCHIVE="$FREEZE_DIR/premium-ui-overrides.css.m10.final"
NGINX_ARCHIVE="$FREEZE_DIR/exswaping-premium-ui-overrides.conf.m10.final"

die() { echo "[premium-ui-restore-m10] ERROR: $*" >&2; exit 1; }

[[ -f "$CSS_ARCHIVE" ]] || die "Missing freeze archive: $CSS_ARCHIVE"
[[ -f "$NGINX_ARCHIVE" ]] || die "Missing freeze archive: $NGINX_ARCHIVE"

echo "[premium-ui-restore-m10] Restoring from $FREEZE_DIR"

if [[ -f "$FREEZE_DIR/SHA256SUMS.txt" ]]; then
  (cd "$FREEZE_DIR" && sha256sum -c SHA256SUMS.txt) || die "SHA256 verification failed"
  echo "[premium-ui-restore-m10] SHA256 OK"
fi

install -m 0644 "$CSS_ARCHIVE" "$CANONICAL"
install -m 0644 "$NGINX_ARCHIVE" "$NGINX_SNIPPET"

echo "[premium-ui-restore-m10] Canonical CSS -> $CANONICAL"
echo "[premium-ui-restore-m10] nginx snippet -> $NGINX_SNIPPET"

"$SCRIPT_DIR/premium-ui-sync-m10-mirrors.sh"

if [[ "${RELOAD_NGINX:-1}" != "0" ]]; then
  nginx -t
  systemctl reload nginx
  echo "[premium-ui-restore-m10] nginx reloaded"
else
  echo "[premium-ui-restore-m10] SKIP nginx reload (RELOAD_NGINX=0)"
fi

echo "[premium-ui-restore-m10] DONE — baseline ?v=$CACHE_VERSION restored"
