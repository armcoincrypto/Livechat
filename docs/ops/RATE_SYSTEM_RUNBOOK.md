# Rate system runbook

## Identities (do not conflate)

- **LIVE backend:** `app.exswaping.com` → release dir; SHA in `RELEASE.json`
- **Deploy state:** `/var/lib/server-ops/exswaping/backend-deploy-state.json` (`current_release` / `previous_release`)
- **Frontend / ops:** owned release + ops runtime (separate)
- `exswaping-backend-releases/current` symlink may lag; **authoritative live is `app.exswaping.com` + deploy-state**

## Compiler / export order (typical)

1. `compiler:bestchange` / courses / generate_prices (ops wrappers + artisan)
2. `scheme:files` (XML rebuild; own lock — not BestChange compiler lock)
3. ExportCourses put → AtomicPublicXmlPublisher
4. Gate show/hide via `RatesXmlMonitorGate`

Last-success markers: `/var/lock/exswaping-compiler-guards/last-success.*`

## Locks

| Lock | Purpose |
|---|---|
| `/var/lock/exswaping-owned-deploy.lock` | Frontend/owned deploy |
| `/var/lock/exswaping-owned-release-owner.lock` | Release-owner lease |
| `/var/lock/exswaping-xml-publish.lock` | Public XML publish |
| `/var/lock/exswaping-artisan-schedule-run.lock` | schedule:run |
| ops `scoped_locks.sh` | build/deploy/scheme-files/bestchange |

Inspect: `scripts/ops/rate_system_lock_status.sh`  
Rule: **never auto-delete** a held lock with a live PID.

## Health checks

```bash
sudo -u app_exswapin_usr /usr/bin/php8.4 -r '/* bootstrap live; RatesXmlPublicFeedHealth::snapshot() */'
bash scripts/ops/rate_system_predeploy_gate.sh /var/www/app_exswapin_usr/data/www/app.exswaping.com
bash scripts/ops/rate_system_lock_status.sh
```

## Recovery (XML empty while online)

1. Confirm SITE_ONLINE / tech mode.
2. Do **not** manually empty currencies.xml.
3. Clear sticky hide only if site online and file is already full (gate `show`).
4. Re-run scheme:files / export under normal locks.
5. Verify `invariant_ok=true`, rows≫0, hide absent.

## Recovery (Zelle margin)

Do not redesign. Restore `floating_fee=-6`, `fix_fee=-3`, `profit=0` on the six freeze ids via deliberate admin/write-gate path; re-run golden gate.

## Rollback

Use immutable previous release from deploy-state. FEATURE_AGENT stops before cutover/rollback. EMERGENCY_ROLLBACK_OPERATOR only with `--expected-current <live-sha>`.
