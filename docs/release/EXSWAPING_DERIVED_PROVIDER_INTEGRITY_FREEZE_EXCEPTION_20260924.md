# FREEZE EXCEPTION — DERIVED MARKET BASELINE PROVIDER INTEGRITY

INCIDENT_ID=GRAM_TON_DERIVED_PROVIDER_POISON  
RATE_SYSTEM_FREEZE=ACTIVE  
LINEAGE_SAFE=YES  
PARENT_SHA=68db8c7b6641ae39bc0fd5d0449404302fe1d664  
DEPLOY_REQUIRED=NO (candidate only)

## ROOT_CAUSE

Binance `TONUSDT` is `BREAK` (empty book / non-trading) but remained `parser_exchange.status=1` at a frozen ~1.60 ticker. `IndependentMarketBaseline` median accepted seven duplicate Binance rows as “healthy”, poisoning all DERIVED GRAM→crypto crosses.

## AFFECTED_SCOPE

Live derived IDs: 132,410,411,412,413,414,415,416,417,418,421,422,426  
Stale/missing quote legs: 419,420,423,424,425  

## FINANCIAL_EXPOSURE

Order 5210 / public 1790151396825905 still PENDING_PAYMENT / unpaid. Settled affected=0. Hypothetical excess if paid ≈0.0044 BTC (~$380).

## WHY_EXISTING_GATE_FAILED

Freeze golden set lacked DERIVED 410; predeploy compared internal surfaces only; duplicate BREAK ticks passed as median.

## PLANNED_GUARDS (implemented in candidate)

- Provider eligibility (BREAK/delisted/stale/no-book)
- Distinct-provider aggregation + outlier rejection
- Certified TONUSDT fallback via BC GRAM peers (ownership unchanged)
- LKG TTL + quarantine on apply failure
- Predeploy counters: DERIVED_BENCHMARK_DEFECT_TOTAL, CROSS_RATE_OUTLIER_TOTAL, UNHEALTHY/DELISTED/STALE_PROVIDER_USED_TOTAL
- Golden includes 410 GRAM→BTC
- Runtime `DerivedProviderHealth`

## RAPIRA / MEXC

Not enabled: parser prices untrustworthy vs CG/BC; MEXC has no real TONUSDT market. Generic policy excludes them until proven.

## EXPECTED_CHANGED_IDS (after deploy refresh)

The 13 incident derived GRAM→crypto IDs above. Profit/position/mapping/Zelle/BestChange formulas unchanged.
