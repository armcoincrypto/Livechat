# schedule-run.lock hygiene (2026-09-27)

## Defect

Cron:

```cron
* * * * * flock -n /var/lock/exswaping-artisan-schedule-run.lock php8.4 …/artisan schedule:run
```

Laravel `runInBackground()` children **inherit** the flock FD. Long jobs
(especially `merchant:webhook` inbound payment polling, often 1–5+ minutes
with ~140 open auto-check tasks) hold `schedule-run.lock` for their full
runtime. Later cron minutes skip **all** scheduled work.

Wave3 crontab comment assumed the lock would be short-lived after
`runInBackground`. FD inheritance violated that intent.

## Fix

Use `scripts/ops/run_schedule_run_once.sh`:

- Parent holds flock until `schedule:run` **returns**
- PHP started with lock FD closed (`9>&-`) so background jobs cannot inherit it
- Per-job `withoutOverlapping` / `onOneServer` / `bestchange-compiler.lock` unchanged

## Crontab cutover (RELEASE_COORDINATOR)

Replace the schedule:run line with:

```cron
* * * * * /var/www/app_exswapin_usr/data/www/app.exswaping.com/scripts/ops/run_schedule_run_once.sh >/dev/null
```

(or ops current symlink once promoted).

## Preserved

- `compiler:bestchange` `withoutOverlapping(20)`
- `run_backend_compiler.sh` bestchange flock
- `MAX_CONCURRENT_BC_WRITERS=1`
- No pricing / ownership changes

## Note on merchant:webhook

Despite the name, this command is **inbound payment detection**
(`checkInPayment` / gateway `fetchPayment`), not outbound merchant webhook
delivery. Remediating outbound webhook workers is out of scope.
