# GRAM DERIVED REGISTRY PERSISTENCE HARDENING

STATUS: CANDIDATE (no deploy)  
BASE_SHA: `ee8515758a9e4cad5c378b07d2917560494dc57c`  
RATE_SYSTEM_FREEZE: ACTIVE  
SCOPE: registry exclusion + gates/tests only — **no pricing / ownership / rate mutation**

## Problem

Wave-1 migrated 18 GRAM/TON directions to BestChange. A concurrent cutover briefly
restored the full `derived-market-baseline-directions.json` (1506 dirs / 18 GRAM IDs
with `block_bestchange_overwrite`). Live was re-stripped, but future releases built
from an unstripped source would ship the poison registry again.

## Permanent excluded IDs

132, 410–418, 419–426 (see `resources/rates/gram-bestchange-migrated-ids.json`).

Invariant: **GRAM_IDS_IN_DERIVED_REGISTRY=0**

## Authoritative source

| Role | Path | SHA256 (stripped) |
|---|---|---|
| Runtime (live) | `…/exswaping-backend-releases/20260924T215912Z-ee8515758a9e/resources/rates/derived-market-baseline-directions.json` | `8542cd51295f1975c510e2ab5b0dfca5e7ab0aaade5ebdb41cccdd64b82a1de5` |
| Candidate source | this branch `resources/rates/derived-market-baseline-directions.json` | same |
| Build input | git-tracked file above (copied into immutable release) | must match |

Historical releases / other worktrees may still contain poison SHA
`3bfaad7f…` (1506 / GRAM=18). Those must not be used as cutover source.

## Gates

1. `scripts/ops/verify_derived_registry_artifact.sh` — SOURCE[/ARTIFACT[/LIVE]] SHA + GRAM exclusion
2. `scripts/ops/rate_system_predeploy_gate.sh` — GRAM registry / owner / block reintroduction counters
3. `scripts/deploy/verify_backend_candidate_integrity.sh` — invokes (1) before cutover
4. PHPUnit `GramBestChangeMigratedRegistryInvariantTest` — including old-registry fail simulation

## Golden

Direction **410 GRAM→BTC**: OWNER=BestChange, not in derived registry,
`block_bestchange_overwrite` absent, BC link active. Same class for all 18.

## Deploy note

STOP before deploy in this change set. Next RELEASE_COORDINATOR cutover must use a
candidate that includes this commit (or equivalent stripped registry + gates).
