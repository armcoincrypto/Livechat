# OAuth Production Delivery Status — 2026-10-20T2116Z

## Live baseline (verified)
| Item | Value |
|------|-------|
| Live FE | `a0e8b4569f92ce4a95777321d0c3e3a1ff1d45aa` (`/opt/exswaping-owned-releases/current`) |
| Live BE | `e394ca14c86ed6013b890138643a6a757fe2f575` (`app.exswaping.com` → `20261020T194642Z-e394ca14c86e`) |
| Prod DB | `app_exswapin` (InnoDB utf8mb4_unicode_ci) |
| `EXS_OAUTH_ENABLED` | ABSENT (disabled) |
| FE OAuth flags | ABSENT / commented (disabled) |

## Ancestry reconciliation (HARD STOP cleared)
OAuth BE was branched from `432d1992` while live advanced by 1 commit (`e394ca14` rates harness).
Action taken: **rebase** oauth commit onto live → new BE HEAD:

`38ed2bb18ed9461c8404fbe242fbc97e4d79ef17`

Ancestry: `e394ca14` ⊂ `38ed2bb` = YES. PHPUnit Auth/OAuth after rebase: **32 PASS**.

FE remains `f63164923e95609a93e44c19655f694dfbc8ed48` (ancestor = live FE).

## MySQL migration certification (isolated)
DB: `exswaping_oauth_mig_cert_20261020` (NOT production)
- Migration up + idempotent: PASS
- Engine/Collation: InnoDB / utf8mb4_unicode_ci (matches users)
- Unique(provider,provider_subject): PASS
- Unique(user_id,provider): PASS
- Production table `user_oauth_identities`: **ABSENT** (not applied)

## Historical Google dry-run (READ-ONLY SELECT on prod)
- Google legacy rows: **8** (1 active, 7 deactivated)
- Yandex legacy: **0**
- Subject conflicts: **0**
- Would insert if table empty: **8**
- No writes performed

## Provider config (DB, secrets redacted)
Table: `social_auth_system`
- google: status=1, client_id=SET (len=71), client_secret=SET
- yandex: status=1, client_id=SET (len=32), client_secret=SET
- Redirect URI column: none (runtime from APP_URL / EXS_OAUTH_*_REDIRECT_URI)
- Computed callbacks with APP_URL=https://exswaping.com:
  - https://exswaping.com/apis/client-api/v1/auth/oauth/google/callback
  - https://exswaping.com/apis/client-api/v1/auth/oauth/yandex/callback
- Live oauth routes today: HTTP 404 (code not deployed)
- IdP console registration of those URIs: **OWNER ACTION REQUIRED** (not verifiable from server alone)

## Session / nginx integration evidence
- `/apis` → Laravel (session 401 JSON)
- Cookies on session: `iexexchanger_session` + `XSRF-TOKEN`, domain `.exswaping.com`, Secure, SameSite=Lax; session HttpOnly
- Password login/register endpoints respond 422 on empty body (alive)
- Logout via GET `/apis/.../auth/logout` currently 404 on live path probe; owned BFF logout is separate — verify post-deploy

## Frontend build
- Capacity gate: load≈8–10, available_mb≈2500 (need load<4.5, mem≥3072)
- Status: **NOT PASS** — waiter running (`/tmp/oauth-fe-build-waiter.log`)
- FE unit contracts: 5 PASS

## Production mutation
NONE. No deploy, no migration apply, no flag enablement, no IdP console changes.

## Owner authorization gate (required before Phase 7)
1. Authorize BE artifact build from SHA `38ed2bb18ed9461c8404fbe242fbc97e4d79ef17` with OAuth disabled.
2. Authorize additive migration on prod after backup.
3. Authorize backfill `--apply` only after dry-run review (8 google rows, 0 conflicts).
4. Register IdP redirect URIs (exact URLs above).
5. Authorize FE build/promote of `f6316492…` (or successor after build) with OAuth flags off.
6. Authorize canary enablement of Google then Yandex.
7. Perform real-browser Google/Yandex acceptance (cannot be fabricated).

### Minimal IdP console steps
**Google Cloud OAuth client (Web):**
1. Authorized redirect URI add: `https://exswaping.com/apis/client-api/v1/auth/oauth/google/callback`
2. Ensure authorized JS origins include `https://exswaping.com` if required by client type
3. Confirm client ID matches `social_auth_system.alias=google` (do not paste secrets)

**Yandex OAuth app:**
1. Callback URI add: `https://exswaping.com/apis/client-api/v1/auth/oauth/yandex/callback`
2. Confirm client ID matches `social_auth_system.alias=yandex`

## Rollback / kill-switch
- Env: omit/`false` `EXS_OAUTH_ENABLED`; FE `OWNED_AUTH_OAUTH_ENABLED` / `NEXT_PUBLIC_*` off
- Symlink rollback via existing deploy `--rollback`
- Do **not** DROP `user_oauth_identities` on rollback

## Admin credential reuse (2026-10-21)
- Source of truth: `social_auth_system.client_id` / `client_secret` via admin AuthSystemController.
- OAuthConfigRepository reads live DB rows (status=1); no env credential overrides allowed.
- Google/Yandex admin rows: SET + VALID_FORMAT (web/Yandex app id shapes).
- Optional env override only for redirect URI (`EXS_OAUTH_*_REDIRECT_URI`); currently ABSENT → computed apex callbacks.
- IdP console callback registration still owner-verified (not proven from DB alone).
