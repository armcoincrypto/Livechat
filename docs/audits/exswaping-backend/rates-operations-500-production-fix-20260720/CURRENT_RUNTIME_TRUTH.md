# CURRENT_RUNTIME_TRUTH — rates/operations 500 fix (2026-07-20)

## Backend identity

| Field | Value |
|-------|-------|
| Path | `/var/www/app_exswapin_usr/data/www/app.exswaping.com` |
| Canonical / live branch | `main` |
| Local HEAD | `ae95d1b1ccf09fcf39c5f28255bc446f4177b3f2` |
| Remote `origin/main` | `ae95d1b1ccf09fcf39c5f28255bc446f4177b3f2` |
| Functional fix | `c2c3e1464e881929475182d23b49eb787383b815` |
| Incident docs tip | `4565600` (pack) → `ae95d1b` (canonicalize identity) |
| PHP CLI for artisan | `/usr/bin/php8.4` |
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

## Deploy / sync method

Live tree **is** the Laravel application root. Fix already live at `c2c3e14`. Canonicalization: checkout `main` on live path; push `origin/main` to `4565600` with writable Livechat deploy key. No migration; no frontend change.

## Concurrent workstreams

Other rate-audit worktrees under `/opt/exswaping-*` remain. `/opt/exswaping-zec-sbp-20260720` left detached at `4565600` so production can own `main`. Unrelated untracked files were **not** committed.
