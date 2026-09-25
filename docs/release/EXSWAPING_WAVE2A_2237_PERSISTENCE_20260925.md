# Wave 2A — NEO→CARDTJS (2237) BestChange ownership persistence

LIVE base: `80f18491dfaef7452b7d009aa9ef21db81c5c648`
Freeze: ACTIVE (scoped exception for single-direction ownership migration)

## Invariant

Direction **2237** MUST:

1. remain absent from `derived-market-baseline-directions.json`
2. have `parser_source_name=BestChange`
3. have active BestChange link (`status=1`)
4. never regain `block_bestchange_overwrite` via derived registry

GRAM Wave-1 guards remain untouched and must continue to pass.

## Registry SHA

After Wave 2A strip (1487 configs):

`525dc61e2403a480f04091f62016d567572132a149cceae34936f8c9b7e35955`

## Gates

- `resources/rates/wave2a-bestchange-migrated-ids.json`
- `scripts/ops/verify_derived_registry_artifact.sh` (forbids GRAM + Wave2A IDs)
- `scripts/ops/rate_system_predeploy_gate.sh` (Wave2A owner/link counters)
- PHPUnit `Wave2aBestChangeMigratedRegistryInvariantTest`
