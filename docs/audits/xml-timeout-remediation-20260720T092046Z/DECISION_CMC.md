# CoinMarketCap decision — 2026-07-20T09:30:01Z

## Verdict
`KEEP_CMC_DISABLED`

## Evidence
- Provider health: disabled; last_errors=104; plan/credential failure warning.
- Active production corridors covered by Binance / WhiteBit / Coinbase / FloatRates.
- No active direction depends exclusively on CMC for a fresh baseline.

## Not chosen
- RENEW_CMC / REPLACE_CMC_KEY — not required for current catalog safety.
- REMOVE_CMC_RUNTIME_DEPENDENCY — leave disabled adapter in place for future probe.

## Re-enable gate
Only after `rates:provider-probe --provider=coinmarketcap` returns HTTP 200 with fresh quotes agreeing with healthy providers.
