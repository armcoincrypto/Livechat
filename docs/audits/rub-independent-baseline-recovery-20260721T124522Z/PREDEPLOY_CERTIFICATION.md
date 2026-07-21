# EXSWAPING RUB independent-baseline recovery — pre-deploy certification

Status: `PREDEPLOY_VERIFIED_NOT_YET_RELEASED`

## Frozen production state

- UTC freeze: `2026-07-21T12:45:22Z`
- production/local/origin SHA: `8967daa20193f7f613a4c716edb2bf8f860cda46`
- deploy drift: `0`
- health critical: `false`
- frozen XML SHA-256: `08567b714972d08e2ba50bccb54ebed289cb719627a562b27b67827bfeeda36b`
- frozen XML items: `704`
- frozen XML RUB rows: `33`
- XML delivery: HTTP 200, valid XML

The scheduled feed moved from 33 to 35 RUB rows while the inventory was being
captured. This is additional evidence that the old BestChange-derived canonical
input made eligibility volatile between otherwise healthy export cycles.

## Source trace

All 69 historically certified directions:

- have an active fresh independent baseline;
- have adequate reserve and verified mapping;
- currently store a BestChange-derived canonical rate;
- can be recalculated by the release code through `Independent RUB`;
- project to `PASS_EXPLAINED_SPREAD` after the existing calculator applies the
  approved family target and current direction adjustments exactly once.

Read-only worktree verification:

```text
directions evaluated: 69
canonical source: Independent RUB (69)
PASS_EXPLAINED_SPREAD: 69
export allowed: 69
circular source detected after projected write: 0
```

Families covered: BNB 4, BTC 8, ETH 6, LTC 6, TRX 1, USDC 7, USDT 35,
ZEC 2.

## Safety behavior

- RUB source selection has no BestChange fallback.
- Only approved parser providers are accepted.
- Missing or stale independent data returns no positive source rate.
- A failed compiler calculation preserves the last stored rate and marks the
  source unavailable; eligibility remains fail-closed.
- Existing `REVIEW`, `QUARANTINE_REQUIRED`, reserve, mapping, and
  `RateExportQuarantine` gates remain active.
- XML and the generated BestChange package share one process-local quote
  snapshot.
- Material RUB catalog movement is recorded and alerted, never auto-restored.

## Verification

```text
PHP syntax: 18 changed PHP files passed
Rates unit suite: 91 tests, 279 assertions passed
Read-only live routing simulation: 69/69 export-eligible
```

Production mutation, XML regeneration, release observation, package parity,
and final verdict remain pending.
