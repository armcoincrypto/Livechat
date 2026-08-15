#!/usr/bin/env bash
# Archive confirmed duplicate/trap files from Laravel public/ (Phase 2).
set -euo pipefail

APP_PUBLIC="/var/www/app_exswapin_usr/data/www/app.exswaping.com/public"
ARCHIVE="/var/www/app_exswapin_usr/data/www/app.exswaping.com/storage/app/archive/duplicate-cleanup/20260701-phase2"
MANIFEST="${ARCHIVE}/MANIFEST.txt"

mkdir -p "${ARCHIVE}/static-seo" "${ARCHIVE}/public-root" "${ARCHIVE}/images-flags" "${ARCHIVE}/public-dist"

move_if_exists() {
  local src="$1"
  local dest_dir="$2"
  local note="$3"
  if [[ -e "$src" ]]; then
    local base
    base="$(basename "$src")"
    mv "$src" "${dest_dir}/${base}"
    echo "[archived] ${src} -> ${dest_dir}/${base} # ${note}" | tee -a "${MANIFEST}"
  fi
}

: > "${MANIFEST}"
echo "Phase 2 archive started $(date -Iseconds)" >> "${MANIFEST}"

# SSR reads storage/seo/homepage-guide-links.ru.html — public copy is HTTP trap only.
move_if_exists \
  "${APP_PUBLIC}/static/seo/homepage-guide-links.ru.html" \
  "${ARCHIVE}/static-seo" \
  "duplicate of storage/seo copy"

# Duplicate Google Search Console verification filename.
move_if_exists \
  "${APP_PUBLIC}/google6283760c7d225723 (1).html" \
  "${ARCHIVE}/public-root" \
  "duplicate GSC verification file"

# Directory-listing HTML trap under flags.
move_if_exists \
  "${APP_PUBLIC}/images/flags/index.html" \
  "${ARCHIVE}/images-flags" \
  "autoindex-style HTML trap"

# Stale Angular dist chunks (live SSR uses exswaping.com/dist/, not app public/dist).
if [[ -d "${APP_PUBLIC}/dist" ]]; then
  mv "${APP_PUBLIC}/dist" "${ARCHIVE}/public-dist/dist"
  echo "[archived] ${APP_PUBLIC}/dist -> ${ARCHIVE}/public-dist/dist # stale SPA build tree (~16M)" >> "${MANIFEST}"
fi

echo "" >> "${MANIFEST}"
echo "NOT archived (investigated, kept or blocked separately):" >> "${MANIFEST}"
echo "  - public/vendor/laravel_backup_panel/* — admin panel assets; block via nginx deny recommended" >> "${MANIFEST}"
echo "  - protected: build/assets, index.php, sitemap.xml, currencies*.xml" >> "${MANIFEST}"

find "${ARCHIVE}" -type f | wc -l | xargs -I{} echo "Files archived: {}" | tee -a "${MANIFEST}"
echo "Archive complete: ${ARCHIVE}"
