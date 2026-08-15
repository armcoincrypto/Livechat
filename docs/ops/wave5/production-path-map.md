# Production path map (Wave 5)

Captured 2026-08-15T21:48:29Z; reconfirmed after source commit `07ab31f`.

## Runtime chain

```
nginx vhost app.exswaping.com
  root = /var/www/app_exswapin_usr/data/www/app.exswaping.com/public/$subdomain
  → public/index.php
  → bootstrap/app.php (Laravel)
  → .env → .../exswaping-backend-shared/config/.env
  → storage/app → .../exswaping-backend-shared/storage/app
  → storage/logs → .../exswaping-backend-shared/storage/logs
  → vendor/autoload.php (live tree)
```

PHP-FPM: `/etc/php/8.4/fpm/pool.d/app.exswaping.com.conf`
user `app_exswapin_usr`, listen `unix:/var/run/app.exswaping.com.sock`, no `chdir`.

Public site FastCGI `SCRIPT_FILENAME` is hardcoded to
`/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/index.php`.
Cutover must keep that path (LIVE symlink → release).

## Current live node

- Path: `/var/www/app_exswapin_usr/data/www/app.exswaping.com`
- Type: directory (not a symlink) during this wave
- Git: same path, branch `main`, remote `git@github.com:armcoincrypto/Livechat.git`

## Split

| Kind | Location |
| SOURCE | Git commit tree (app, packages, routes, config, bootstrap, resources, artisan, xml-changer) |
| SHARED_STATE | `.env`, licenses, storage/app, storage/logs, public-state |
| RUNTIME | storage/framework, bootstrap/cache, php-fpm tmp |
| DEPLOY_ARTIFACT | public-app hashed JS snapshot |
