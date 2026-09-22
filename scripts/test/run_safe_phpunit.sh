#!/usr/bin/env bash
# Fail-closed PHPUnit runner for disposable exswaping_phpunit_* databases only.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

export APP_ENV=testing
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-exswaping_phpunit_20260920}"
export DB_USERNAME="${DB_USERNAME:-exswaping_phpunit}"
export DB_PASSWORD="${DB_PASSWORD:-exswaping_phpunit_dummy}"
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export DIDIT_BASE_URL="${DIDIT_BASE_URL:-http://didit.invalid}"
export DIDIT_API_KEY="${DIDIT_API_KEY:-}"
export TELEGRAM_OPERATOR_BOT_TOKEN="${TELEGRAM_OPERATOR_BOT_TOKEN:-test-only-not-a-real-token}"
export VAULT_MASTER_KEY="${VAULT_MASTER_KEY:-test-only-vault-master-key-32b!!}"

if [[ "${DB_DATABASE}" == "app_exswapin" ]]; then
  echo "REFUSED: production database" >&2
  exit 2
fi
if [[ "${DB_DATABASE}" != exswaping_phpunit* ]]; then
  echo "REFUSED: DB_DATABASE must be exswaping_phpunit*" >&2
  exit 2
fi
if [[ "${DB_USERNAME}" != "exswaping_phpunit" ]]; then
  echo "REFUSED: DB_USERNAME must be exswaping_phpunit" >&2
  exit 2
fi

bash "${ROOT}/scripts/test/apply_phpunit_schema_patch.sh"
exec /usr/bin/php8.4 vendor/bin/phpunit --testsuite Unit "$@"
