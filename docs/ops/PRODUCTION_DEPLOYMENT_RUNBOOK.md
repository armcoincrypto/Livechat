# Exswaping backend production deployment

Canonical Git: overlay repo `main` at `/var/www/app_exswapin_usr/data/www/app.exswaping.com`
Do **not** merge `origin/main` (unrelated July baseline, behind/ahead).
Do **not** deploy from the dirty live worktree.

## NEVER

- `git clean` on the production overlay
- `git reset --hard` while protected WIP exists
- `git restore .` on the overlay
- Point nginx at an unverified candidate
- Promote a release with LIVE overlay backreferences
- Run two deploys at once (mutate lock)

## Prepare

1. Preserve overlay WIP (`UNRELATED_WIP` / `UNKNOWN_PROTECT`) outside the release tree.
2. Commit production-required source only (no `.env`, licenses, `vendor/`, SQL dumps).
3. Confirm `PRODUCTION_REQUIRED_DIRT=0` via `scripts/ops/assert_backend_source_dirt.sh`.

## Candidate

```bash
export EXSWAPING_BACKEND_GIT_DIR=/var/www/app_exswapin_usr/data/www/app.exswaping.com
export EXSWAPING_APPROVED_SHA=$(git -C "$EXSWAPING_BACKEND_GIT_DIR" rev-parse HEAD)
export COMPOSER_ALLOW_SUPERUSER=1
/opt/exswaping-owned-frontend/scripts/deploy/deploy_backend_release.sh build "$EXSWAPING_APPROVED_SHA"
```

Build uses `git worktree add` of that SHA (not overlay copy), shared `.env`/`storage`/`licenses`, `composer install --no-dev --prefer-dist --no-interaction`.

## Test

- `scripts/ops/validate_backend_candidate.sh <release-dir>`
- `scripts/deploy/switch_backend_release.sh preflight <release-dir>`
- `nginx -t`
- `php8.4 artisan about` and `route:list --path=client-api` on the candidate
- Hollow synthetic candidate must fail validation

## Deploy

```bash
export EXSWAPING_APPROVED_SHA=<full-sha>
/opt/exswaping-owned-frontend/scripts/deploy/switch_backend_release.sh cutover <release-dir>
```

Cutover: validate → atomic `LIVE.next` + `mv -T` (directory overlay renamed to `app.exswaping.com.legacy-*` first) → php-fpm reload → health → workers.

FastPanel keeps `SCRIPT_FILENAME` at `.../app.exswaping.com/public/index.php`. LIVE must remain that path (symlink to the release).

## Verify

- Homepage 200
- `https://exswaping.com/apis/client-api/v1/tech` 200
- `.../start` 200
- Quote / funded-health / BestChange XML + membership + deeplink fast gates as currently certified
- `scripts/ops/monitor_backend_source_integrity.sh`

## Rollback

```bash
/opt/exswaping-owned-frontend/scripts/deploy/switch_backend_release.sh rollback
```

Restores the recorded legacy overlay directory. Automatic rollback runs if post-cutover health fails.

## Emergency live edit (emergency-only)

Overlay edit + php-fpm reload is **emergency-only**. Normal path: edit → commit → candidate → test → deploy.

## Retention

Keep current, previous known-good, and a small number of recent `exswaping-backend-releases/*`. Do not delete historic releases unless classified.

## Migrations

This architecture does not run migrations on cutover. If a future release contains new migrations: detect via `php artisan migrate --pretend`, require DB backup, and treat rollback as source-only (schema is not auto-reversed).
