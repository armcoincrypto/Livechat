# CURRENT_RUNTIME_TRUTH — rates/operations 500 fix (2026-07-20)

## Backend identity

| Field | Value |
|-------|-------|
| Path | `/var/www/app_exswapin_usr/data/www/app.exswaping.com` |
| Branch (live checkout) | `fix/rates-operations-partial-currency-load-20260720` |
| `main` tip (worktree `/opt/exswaping-zec-sbp-20260720`) | same SHA |
| Starting SHA (investigation) | `13431b0f6204aee44b96b5b164ab1667cb4471f0` |
| Fix SHA | `c2c3e1464e881929475182d23b49eb787383b815` |
| PHP CLI for artisan | `/usr/bin/php8.4` (default `php` 8.5 cannot load ionCube) |
| PHP-FPM | `php8.4-fpm` pool `app.exswaping.com` → `unix:/var/run/app.exswaping.com.sock` |
| Laravel | 12.62.0 |
| Environment | production, debug OFF |
| Cache driver | redis |
| Queue | redis (Horizon + `queue:work`) |
| Config cache | NOT CACHED |
| Route cache | NOT CACHED |
| View cache | CACHED |

## Frontend (unchanged)

| Field | Value |
|-------|-------|
| Release | `/opt/exswaping-owned-releases/20260720T174951Z-7414aef5611a` |
| SHA | `7414aef5611a8b69ac4abb6aef03d61530ad82f1` |
| PM2 | `exswaping-owned-production` online on `:3010` |
| Marker | `data-exs-visual-redesign="20260720"` on `/en/` |

## Deploy method

Live tree **is** the Laravel application root. Fix committed on production path; `systemctl reload php8.4-fpm` for opcache worker refresh. No migration required.

## Concurrent workstreams

Multiple rate-audit worktrees under `/opt/exswaping-*` exist. Functional fix isolated to `RateDirectionEligibility` + unit test. Unrelated untracked files were **not** committed.
