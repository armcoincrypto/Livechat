# Production canary plan — scheme scheduled skip

**Candidate:** `c09b37cb781041318c09e3248d3296e8f9d730c4` on live ancestry `406c081e2c01`  
**Flag:** `SCHEME_FILES_SCHEDULED_SKIP_UNCHANGED` default **false**  
**Do not execute without explicit production authorization.**

## Preconditions
1. Live SHA still `406c081e2c01…` (or re-rebase candidate).
2. Backup / prior release symlink available for rollback.
3. Book-reuse / SKIP_IDENTICAL / OAuth untouched by this canary.
4. Unit tests 22/22 PASS on candidate.

## Sequence (after authorization)
1. Verify live SHA + backups.
2. Deploy candidate with flag **OFF**.
3. Confirm scheduled + post-BC exports behave as today (full path).
4. Allow one complete successful `scheme:files` to seed `scheme_files.scheduled_skip.meta.v1`.
5. Confirm XML sha + RU/EN cache presence match meta.
6. Enable flag **true** for controlled canary window only.
7. Observe unchanged ticks → log `scheme_files_scheduled_skip` reason=unchanged_complete_inputs; wall ≪ full.
8. Force a controlled input change (or wait for rate change) → full export runs.
9. Confirm post-BC always runs (invocation=post-bc never skips).
10. Verify rate freshness (quote soft-poll) and XML item health.
11. Watch CPU / MySQL / app health / publication logs.

## Abort
Any failed prerequisite → leave flag false; do not enable globally.
Kill-switch: `/opt/exswaping-owned-ops/p0-server-perf-20261021T002921Z/scheme-canary-cert-20261022T012518Z/rollback/KILL_SWITCH_set_scheme_skip_false.sh`
