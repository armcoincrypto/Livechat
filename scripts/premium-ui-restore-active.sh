#!/usr/bin/env bash
#
# P-UI-MASTER-20 — Restore active production UI baseline (m19 final)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

FREEZE_DIR="${PREMIUM_UI_FREEZE_DIR:-/root/exswaping-nginx-backups/P-UI-MASTER-20-FREEZE}"
CANONICAL="${PREMIUM_UI_CANONICAL:-$ROOT/public/static/premium-ui-overrides.css}"
NGINX_SNIPPET="${PREMIUM_UI_NGINX_SNIPPET:-/etc/nginx/snippets/exswaping-premium-ui-overrides.conf}"
CACHE_VERSION="${PREMIUM_UI_CACHE_VERSION:-20260627m19}"

CSS_ARCHIVE="$FREEZE_DIR/premium-ui-overrides.css.m19.final"
NGINX_ARCHIVE="$FREEZE_DIR/exswaping-premium-ui-overrides.conf.m19.final"

die() { echo "[premium-ui-restore-active] ERROR: $*" >&2; exit 1; }

[[ -f "$CSS_ARCHIVE" ]] || die "Missing freeze archive: $CSS_ARCHIVE"
[[ -f "$NGINX_ARCHIVE" ]] || die "Missing freeze archive: $NGINX_ARCHIVE"

echo "[premium-ui-restore-active] Restoring from $FREEZE_DIR"

if [[ -f "$FREEZE_DIR/SHA256SUMS.txt" ]]; then
  (cd "$FREEZE_DIR" && sha256sum -c SHA256SUMS.txt) || die "SHA256 verification failed"
  echo "[premium-ui-restore-active] SHA256 OK"
elif [[ -f "$FREEZE_DIR/SHA256SUMS.final.txt" ]]; then
  (cd "$FREEZE_DIR" && sha256sum -c SHA256SUMS.final.txt) || die "SHA256 verification failed"
  echo "[premium-ui-restore-active] SHA256 OK"
fi

install -m 0644 "$CSS_ARCHIVE" "$CANONICAL"
install -m 0644 "$NGINX_ARCHIVE" "$NGINX_SNIPPET"

"$SCRIPT_DIR/premium-ui-sync-active-mirrors.sh"

if [[ "${RELOAD_NGINX:-1}" != "0" ]]; then
  nginx -t
  systemctl reload nginx
  echo "[premium-ui-restore-active] nginx reloaded"
fi

echo "[premium-ui-restore-active] DONE — active baseline ?v=$CACHE_VERSION restored"
