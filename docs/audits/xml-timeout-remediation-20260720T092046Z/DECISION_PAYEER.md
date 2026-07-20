# Payeer decision — 2026-07-20T09:30:01Z

## Codes
`PRUSD`, `PREUR`, `PRRUB`

## Verdict
`KEEP_INTERNAL_ONLY` / public export fail-closed (`HISTORICAL_ONLY` for BestChange)

## Mapping
- All three remain **ABSENT** in BestChange mapping registry.
- BestChange ID **108 = CARDVND** — must not be reused for PRUSD.

## Public XML
Absent from canonical `/currencies.xml` after mapping fail-closed export filter.

## Remap
`REMAP_TO_VERIFIED_CURRENT_IDENTITIES` blocked until operator supplies verified BC IDs.
