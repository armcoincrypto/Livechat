# cs.created_at exclusion proof

## Where it is written
`Courses::builder()` sets `cs.created_at = Carbon::now()` on every locale cache rebuild.
It is **not** read from DB and is **not** an input to XML assembly.

## Does changing only cs.created_at require regeneration?

| Output | Driven by created_at? | Verdict |
|--------|----------------------|---------|
| currencies.xml | No — Export path never references created_at | NO |
| RU/EN redis catalog semantic fields | No — direction_rates, reserves, filters, paymentSystems independent | NO |
| Exchange cache (same redis keys) | Stamp-only field; semantic content unchanged if inputs unchanged | NO (stamp advances only if rebuild runs) |
| dr_snapshot_id | Independent Cache counter; not derived from created_at | NO |
| External XML publication | Atomic publish of rates items only | NO |
| FE consumer freshness | Quote soft-poll 15s via `/rates/operations`; `dr_snapshot_id`/`cs.created_at` typed but unused for gating | NO |

## Conclusion
Excluding `cs.created_at` from the complete input fingerprint is **correct**.
On skip, caches keep the prior `created_at` (last semantic rebuild time) — accurate.
If inputs are unchanged, advancing created_at alone would be a false freshness signal.
