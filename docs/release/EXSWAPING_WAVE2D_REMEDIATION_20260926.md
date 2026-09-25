# FREEZE EXCEPTION — WAVE 2D REMEDIATION / OWNERSHIP CLEANUP

INCIDENT_ID=WAVE2D_2870_ETC_PAYTMINR_PLUS_MAPPING_HARDENING
RATE_SYSTEM_FREEZE=ACTIVE (remains ACTIVE; exception scoped below)
LINEAGE_SAFE=YES
LIVE_BACKEND_SHA_BEFORE=37316ab9d962839b94beb7ff992a85ad1b928e13
ROLE=RELEASE_COORDINATOR

## SCOPE
1. Migrate ONLY direction 2870 ETC→PAYTMINR DERIVED→BestChange.
2. Repair mapping metadata for 1482 and 1513 WITHOUT ownership change.
3. Persist Payeer no-exact-BC-rail policy + catalog critical-code gate.
4. No PREUR/PRRUB/PRUSD BC activation. No profit/position/Zelle/GRAM changes.

## ALLOWED MUTATIONS
- derived-market-baseline-directions.json: remove key "2870"
- bestchange_directions for 2870: status 0→1; exchange_out label → PAYTMINR (id stays 292)
- direction_exchange.parser_source_name for 2870: DERIVED_MARKET_BASELINE→BestChange
- bestchange_directions currency ids/labels for 1482 (23→89 out) and 1513 (89→105) only; status stays 0
- Policy/manifest/gate/test artifacts listed in Wave 2D commit

## FORBIDDEN
- Migrating 1482/1513 ownership
- Profit/position/min/max/reserve/tasks mutation
- Mapping Payeer to CARDARS/CARDVND/PMUSD/ZENEUR/ADVCEUR
- Weakening GRAM/Wave2A persistence or Vite/admin fail-closed gates

## ROLLBACK
Restore registry entry 2870 + BC/status/parser snapshots from
`/var/lib/exswaping-owned/pricing/wave2d-remediation-20260926/rollback/`.
