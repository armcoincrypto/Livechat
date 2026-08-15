#!/usr/bin/env bash
# Archive public crawler-trap files outside web root (no delete).
set -euo pipefail

APP_ROOT="/var/www/app_exswapin_usr/data/www/app.exswaping.com"
STAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE="${APP_ROOT}/storage/app/archive/seo-public-traps/${STAMP}"

mkdir -p "${ARCHIVE}/exports" "${ARCHIVE}/public-root" "${ARCHIVE}/static-seo-html"

move_if_exists() {
  local src="$1"
  local dest_dir="$2"
  if [[ -f "$src" ]]; then
    mv "$src" "${dest_dir}/$(basename "$src")"
    echo "archived: $src"
  fi
}

move_if_exists "${APP_ROOT}/public/static/exports/currencies-bak.xml" "${ARCHIVE}/exports"
move_if_exists "${APP_ROOT}/public/static/exports/currencies.xml.bak.2026-04-09-110300" "${ARCHIVE}/exports"
move_if_exists "${APP_ROOT}/public/sitemap (1).xml" "${ARCHIVE}/public-root"
move_if_exists "${APP_ROOT}/public/currencies.xml.bak.2026-04-09-105712" "${ARCHIVE}/public-root"

shopt -s nullglob
for f in "${APP_ROOT}/public/static/seo/"*.html; do
  base="$(basename "$f")"
  if [[ "$base" == "sitemap.xml" ]]; then
    continue
  fi
  mv "$f" "${ARCHIVE}/static-seo-html/"
  echo "archived: $f"
done

echo "Archive complete: ${ARCHIVE}"
find "${ARCHIVE}" -type f | wc -l | xargs -I{} echo "Files archived: {}"
