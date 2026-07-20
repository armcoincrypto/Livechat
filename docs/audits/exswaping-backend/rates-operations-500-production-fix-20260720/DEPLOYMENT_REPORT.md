# DEPLOYMENT_REPORT

## Canonical path

Production Laravel root **is** `/var/www/app_exswapin_usr/data/www/app.exswaping.com` (no separate release symlink for PHP app).

## Steps performed

1. Functional commit already on live tree: `c2c3e1464e881929475182d23b49eb787383b815`
2. `main` tip aligned to same SHA (worktree `/opt/exswaping-zec-sbp-20260720`)
3. `systemctl reload php8.4-fpm` (USR2) — graceful opcache/worker refresh
4. No `composer install`
5. No migrations
6. No config/route cache rebuild (were already uncached)
7. Horizon / queue workers left running (no restart required for this PHP file change on FPM request path)

## Identity

| Item | Value |
|------|-------|
| Starting SHA | `13431b0f6204aee44b96b5b164ab1667cb4471f0` |
| Final SHA | `c2c3e1464e881929475182d23b49eb787383b815` |
| PHP-FPM before | active since 2026-07-16; pool workers serving stale/poisoned load path |
| PHP-FPM after | reload OK; new pool workers `app.exswaping.com` / `www` |
| Queue before/after | Horizon + queue:work continuous; unchanged |
| Migration result | none |
| Downtime | none observed (graceful reload) |

## Exact commands

```bash
# code already at c2c3e14 on live path
systemctl reload php8.4-fpm
```

## Remote canonicalize (2026-07-20T20:14Z)

```bash
git checkout main   # live path now on canonical branch
GIT_SSH_COMMAND='ssh -i /root/.ssh/exswaping_livechat_deploy -o IdentitiesOnly=yes' \
  git push origin main
# 6d5ddbe..4565600  main -> main
```

Final: `origin/main` = `4565600` = local `main` = live HEAD.

## Rollback command

```bash
cd /var/www/app_exswapin_usr/data/www/app.exswaping.com
git checkout 13431b0f6204aee44b96b5b164ab1667cb4471f0 -- app/Services/Rates/RateDirectionEligibility.php
# or: git revert c2c3e14
systemctl reload php8.4-fpm
```

**Warning:** rolling back restores the partial `loadMissing` and re-breaks operations 500.
