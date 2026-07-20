# ROLLBACK

## Rollback SHA

`13431b0f6204aee44b96b5b164ab1667cb4471f0` (parent of fix)

## Commands

```bash
cd /var/www/app_exswapin_usr/data/www/app.exswaping.com
git revert --no-edit c2c3e1464e881929475182d23b49eb787383b815
# OR restore single file from parent:
# git checkout 13431b0f6204aee44b96b5b164ab1667cb4471f0 -- app/Services/Rates/RateDirectionEligibility.php
systemctl reload php8.4-fpm
```

## Verify rollback (expect failure again)

```bash
curl -sS -o /dev/null -w '%{http_code}\n' \
  'https://exswaping.com/apis/client-api/v1/rates/operations/USDTTRC20/SBERRUB'
# expect 500 if partial load restored
```

## Do not

- Roll back frontend redesign `7414aef` for this incident
- Disable RUB policy to “fix” operations
- Force-reset the repository
