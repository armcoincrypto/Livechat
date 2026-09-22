#!/usr/bin/env bash
# Apply disposable PHPUnit schema patch. Fail-closed against production DBs.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PATCH="${ROOT}/database/testing/phpunit_schema_patch.sql"
DB_NAME="${DB_DATABASE:-exswaping_phpunit_20260920}"
DB_USER="${DB_USERNAME:-exswaping_phpunit}"
DB_PASS="${DB_PASSWORD:-exswaping_phpunit_dummy}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

if [[ "${DB_NAME}" == "app_exswapin" ]]; then
  echo "REFUSED: refusing to patch production database name app_exswapin" >&2
  exit 2
fi
if [[ "${DB_NAME}" != exswaping_phpunit* ]]; then
  echo "REFUSED: DB_DATABASE must start with exswaping_phpunit (got ${DB_NAME})" >&2
  exit 2
fi
if [[ "${DB_USER}" != "exswaping_phpunit" ]]; then
  echo "REFUSED: DB_USERNAME must be exswaping_phpunit" >&2
  exit 2
fi
if [[ ! -f "${PATCH}" ]]; then
  echo "REFUSED: missing ${PATCH}" >&2
  exit 2
fi

mysql --no-defaults --protocol=tcp -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" \
  "${DB_NAME}" < "${PATCH}"

MISSING_TABLES="${ROOT}/database/testing/phpunit_missing_tables.sql"
MISSING_COLS="${ROOT}/database/testing/phpunit_missing_columns.sql"
if [[ -f "${MISSING_TABLES}" ]]; then
  mysql --no-defaults --protocol=tcp -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" \
    "${DB_NAME}" < "${MISSING_TABLES}"
fi
if [[ -f "${MISSING_COLS}" ]]; then
  mysql --no-defaults --protocol=tcp -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" \
    "${DB_NAME}" < "${MISSING_COLS}" || true
fi

echo "PHPUNIT_SCHEMA_PATCH_OK db=${DB_NAME}"
