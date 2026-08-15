#!/usr/bin/env bash
#
# P-UI-MASTER-12 — Restore premium UI baseline (m12)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

FREEZE_DIR="${PREMIUM_UI_FREEZE_DIR:-/root/exswaping-nginx-backups/P-UI-MASTER-12-FREEZE}"
CANONICAL="${PREMIUM_UI_CANONICAL:-$ROOT/public/static/premium-ui-overrides.css}"
NGINX_SNIPPET="${PREMIUM_UI_NGINX_SNIPPET:-/etc/nginx/snippets/exswaping-premium-ui-overrides.conf}"
CACHE_VERSION="${PREMIUM_UI_CACHE_VERSION:-20260627m12}"

CSS_ARCHIVE="$FREEZE_DIR/premium-ui-overrides.css.m12.final"
NGINX_ARCHIVE="$FREEZE_DIR/exswaping-premium-ui-overrides.conf.m12.final"

die() { echo "[premium-ui-restore-m12] ERROR: $*" >&2; exit 1; }

[[ -f "$CSS_ARCHIVE" ]] || die "Missing freeze archive: $CSS_ARCHIVE"
[[ -f "$NGINX_ARCHIVE" ]] || die "Missing freeze archive: $NGINX_ARCHIVE"

echo "[premium-ui-restore-m12] Restoring from $FREEZE_DIR"

if [[ -f "$FREEZE_DIR/SHA256SUMS.txt" ]]; then
  (cd "$FREEZE_DIR" && sha256sum -c SHA256SUMS.txt) || die "SHA256 verification failed"
  echo "[premium-ui-restore-m12] SHA256 OK"
fi

install -m 0644 "$CSS_ARCHIVE" "$CANONICAL"
install -m 0644 "$NGINX_ARCHIVE" "$NGINX_SNIPPET"

"$SCRIPT_DIR/premium-ui-sync-m10-mirrors.sh"

if [[ "${RELOAD_NGINX:-1}" != "0" ]]; then
  nginx -t
  systemctl reload nginx
  echo "[premium-ui-restore-m12] nginx reloaded"
fi

echo "[premium-ui-restore-m12] DONE — baseline ?v=$CACHE_VERSION restored"
