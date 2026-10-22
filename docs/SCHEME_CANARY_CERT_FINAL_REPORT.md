# Scheme scheduled-skip — production canary certification

Evidence: `/opt/exswaping-owned-ops/p0-server-perf-20261021T002921Z/scheme-canary-cert-20261022T012518Z/`

---

VERDICT=CANARY_READY_ISOLATED; AWAITING_EXPLICIT_PROD_AUTH; NOT_DEPLOYED

LIVE_BE_SHA=406c081e2c01dc35fe2d00a6c30b764a708bc748

NEW_CANDIDATE_SHA=9d87f756867184bbcbf7302f54514f7598b3d5dc

ANCESTRY=PASS — candidate = cherry-pick of c656093 onto live tip 406c081 + cert docs; files limited to scheme-skip + docs/kill-switch/tests

FINGERPRINT_CONTRACT=PASS — complete v1 inputs; time-window open-mask included; fail-open on uncertainty

CS_CREATED_AT_EXCLUSION=PASS — stamp-only in Courses::builder; not used by XML export; FE does not gate freshness on it; exclusion correct

SNAPSHOT_CONSUMER_PARITY=PASS — writers: scheme:files + ExchangeRatesCacheInvalidator; FE types dr_snapshot_id but does not poll it; unchanged tick may keep ID; fail-open otherwise

TESTS=22/22 PASS (SchemeFilesScheduledSkipGateTest on reconciled tree)

FLAG_DEFAULT=SCHEME_FILES_SCHEDULED_SKIP_UNCHANGED=false (absent in live .env ⇒ off)

CANARY_READY=YES (isolated plan ready; requires explicit production authorization to deploy/enable)

BACKUP_READY=YES — prior releases previous/20261021T212459Z-19a9de159631; ops backup scripts present; live ROLLBACK.md present

ROLLBACK_READY=YES — kill-switch scripts/ops/KILL_SWITCH_set_scheme_skip_false.sh (EXS_OWNER_APPROVED=1); preserves skip meta

PRODUCTION_MUTATED=NO

PRODUCTION_DB_MUTATED=NO

FINANCIAL_DATA_MUTATED=NO

REAL_FUNDS_MOVED=NO

NEXT_ACTION=Await explicit production authorization to deploy candidate 9d87f75 with flag OFF, seed meta via one full export, then enable scheduled-only canary per docs/CANARY_PLAN.md

---

## Phase notes

### Live advanced
Original implementation c656093 was on 19a9de1. Live moved to 406c081. Reconciled without overwriting: new worktree + cherry-pick → c09b37c, cert → 9d87f75.

### Live note (out of scheme scope)
406c081 already ships BestChange generation-context book reuse in recalc (not introduced by this candidate). Scheme canary does not modify that path. Env has no SCHEME_FILES_* flag; L1 shadow/skip-identical remain false; SAME_SNAPSHOT diagnostic still true under L1 lease.

### Unrelated promotion
Diff vs live tip is only: StatusRatesFilesCommand, SchemeFilesScheduledSkipGate, unit tests, docs, kill-switch.
