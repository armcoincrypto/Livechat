# dr_snapshot_id consumer audit

## Writers
1. `StatusRatesFilesCommand::updateExchangeDirections` — increment after locale rebuild
2. `ExchangeRatesCacheInvalidator::rebuildPublicRatesSnapshots` — increment after admin rebuild

## Readers / embedders
1. `Courses::builder` embeds current `Cache::get('dr_snapshot_id')` into redis payload `cs.dr_snapshot_id`
2. Frontend TypeScript type includes `cs.dr_snapshot_id` — **no runtime compare/poll found**
3. FE quote freshness uses 15s soft poll of live quote endpoints, not snapshot id

## Skip policy
Unchanged scheduled tick **may leave snapshot ID unchanged**.
Rationale: snapshot exists to signal catalog content generations; unchanged content → same ID is consistent.
Fail-open: any uncertainty (missing meta, cache miss, fp change, offline, monitor) → full export + increment.

## Parity
SNAPSHOT_CONSUMER_PARITY=PASS — no consumer requires monotonic advance on no-op ticks.
