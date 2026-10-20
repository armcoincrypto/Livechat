# OAuth Google + Yandex — Release Certification

Date: 2026-10-20
Worktrees (isolated only):
- FE `/opt/exswaping-owned-worktrees/oauth-google-yandex-20261020T200136Z`
- BE `/opt/exswaping-backend-worktrees/oauth-google-yandex-20261020T200136Z`

## Hardening applied in this certification pass
- Legacy `users.provider` / `provider_id` resolution before email-collision / new-user (historical Google).
- Authenticated account linking with password reauth (`POST /auth/oauth/reauth`, 10-minute session TTL).
- Fail-closed `OAuthReturnToSanitizer` (locale-relative paths only).
- Audit logging via AuthAudit (no tokens/codes).
- Concurrent subject conflict handling (unique constraints + QueryException mapping).
- Settings UI: `AccountOAuthLinkPanel` (reauth → provider redirect; no email-only link).

## Evidence
| Gate | Result |
|------|--------|
| BE PHPUnit Auth/OAuth | **32 passed / 0 failed / 116 assertions** |
| Migration isolated sqlite | **OK + idempotent** (`MIGRATION_ISOLATED_OK`) |
| Composer validate | **valid** |
| PHP `-l` OAuth sources | **OK** |
| FE node:test oauth contracts | **5 passed / 0 failed** |
| FE lint / tsc / next build | **SKIPPED** — heavy-job RESOURCE_CONFLICT (load/memory) |
| Live `/apis/.../oauth/*/callback` | HTTP **404** (routes not on live SHA; `/apis` still reaches Laravel) |
| Live `/apis/.../session` | HTTP **401** JSON (session stack alive) |
| Nginx `location /apis` + `@apis` | Present on apex vhost |
| Production mutate | **NO** |

## Expected callback URLs (register in IdP consoles before enablement)
- `https://exswaping.com/apis/client-api/v1/auth/oauth/google/callback`
- `https://exswaping.com/apis/client-api/v1/auth/oauth/yandex/callback`

Route registration (worktree source):
`packages/ExchangerClient/routes/client-api.php` → `auth/oauth/{google|yandex}/redirect|callback` + `auth/oauth/reauth` + `auth/oauth/identities`.

## Safe deployment ordering (do not execute until owner approval)
1. Deploy BE release with `EXS_OAUTH_ENABLED=false`.
2. Run migration on non-prod, then prod: `user_oauth_identities`.
3. `php artisan auth:oauth-identities-backfill --dry-run --provider=google` — review conflicts.
4. Apply backfill only if dry-run clean: `--apply --provider=google`.
5. Register IdP redirect URIs; confirm client id/secret in `social_auth_systems` (status=1).
6. Deploy FE with `OWNED_AUTH_OAUTH_ENABLED` / `NEXT_PUBLIC_OWNED_AUTH_OAUTH_ENABLED` still false.
7. Smoke password login/register/logout.
8. Enable BE flag, then FE flag; monitor logs for `oauth.*` / AuthAudit.
9. Acceptance: new Google, returning Google, legacy Google (active), deactivated Google denied, Yandex new/returning, link flow with reauth, cancellation, disabled flag.

## Rollback
- Set `EXS_OAUTH_ENABLED=false` and FE OAuth flags false (immediate kill switch).
- Redeploy previous BE/FE SHAs if needed.
- Do **not** drop `user_oauth_identities` unless a full rollback plan is approved (data loss).

## Monitoring
- AuthAudit events: `oauth_login_success|register|link|failed|denied`
- Laravel log keys: `oauth.redirect.failed`, `oauth.callback.failed` (codes only)
- Rate limit: `throttle:20,1` on oauth group; `throttle:10,1` on reauth

## Production readiness blockers
1. Owner-approved deploy + migration + IdP console registration.
2. Capacity window for FE typecheck/build certification.
3. Live route smoke after BE deploy (expect non-404 when enabled).
