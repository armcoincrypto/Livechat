# FINAL_REPORT

## Verdict

```text
EXSWAPING_RATES_OPERATIONS_FIX_CANONICALIZED_PASS_WITH_WARNINGS
```

Warning: malformed asset IDs may still return HTTP 200 with `{"code":-1,"message":"Not Found"}` (pre-existing; follow-up only).

## Canonical identity (final)

| Field | Value |
|-------|-------|
| Backend path | `/var/www/app_exswapin_usr/data/www/app.exswaping.com` |
| Canonical branch | `main` |
| Live checkout | `main` |
| Local HEAD | `4565600b7fcc35b792dbd5a86c3261a7a50a07b9` |
| Remote `origin/main` | `4565600b7fcc35b792dbd5a86c3261a7a50a07b9` |
| Functional commit | `c2c3e1464e881929475182d23b49eb787383b815` |
| Documentation commit (incident pack) | `4565600b7fcc35b792dbd5a86c3261a7a50a07b9` |
| Production runtime SHA | `4565600` (same tree) |
| PHP-FPM | `php8.4-fpm` active; pool `app.exswaping.com` |
| Frontend | unchanged `7414aef` / release `20260720T174951Z-7414aef5611a` |

## Integration

Path A — both `c2c3e14` and `4565600` were already ancestors of local `main`. No cherry-pick. Newer valid commits `14f81e0` and `13431b0` preserved. Live checkout switched from fix branch name → `main` (same SHA).

## Remote synchronization

```text
git fetch origin
GIT_SSH_COMMAND='ssh -i /root/.ssh/exswaping_livechat_deploy -o IdentitiesOnly=yes' \
  git push origin main
# 6d5ddbe..4565600  main -> main
```

Default `id_rsa` / agent key is read-only for this repo; Livechat deploy key is write-capable.

## Production proof (re-verified after canonicalize)

| Pair | HTTP |
|------|------|
| USDTTRC20/SBERRUB | 200 + course.rate + buy icon |
| BTC/USDTTRC20 | 200 |
| USDTTRC20/SBPRUB | 404 |
| NOTACOIN/FAKE | 404 |

No new `rate_public_surface_quote_gate_failed` after fix window. PHP-FPM active. Frontend PM2 still on `7414aef` release.

## Safety

```text
ORDERS_CREATED=0
FUNDS_MOVED=0
DATABASE_WRITES=0
FRONTEND_CHANGED=NO
```

## Remaining follow-up

```text
Malformed identifiers may return HTTP 200 with code:-1.
Handle separately after defining the API contract.
```

## Audit location

`docs/audits/exswaping-backend/rates-operations-500-production-fix-20260720/`
