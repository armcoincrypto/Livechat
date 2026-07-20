# Provider / baseline coverage summary

Companion to `PROVIDER_COVERAGE.json`. Detail from [Audit parser provider coverage](0452dcf3-4fc7-477b-8b6c-d9bfb9e0f225).

## Independent vs circular

- **Approved:** enabled `parser_exchange` (median, age-gated) → `IndependentMarketBaseline`
- **Rejected as independent mid:** BestChange `rate_value` / BC peer median when the direction is BC-priced

## Live coverage (this audit)

| Asset | Path |
|---|---|
| BTC, ETH, BNB, TRX, ZEC, LTC | USDT_BRIDGE (`*USDT` × USDRUB) |
| USDT / USDC → RUB | FIAT_CONVERSION (USDRUB) |
| TON | UNSUPPORTED_OR_STALE — KEEP_TON_DISABLED |

## Detection criteria

| Issue | Signal |
|---|---|
| Stale | age >900s crypto / >6h fiat |
| Disabled | `parser_exchange.status=0` or group disabled |
| Duplicates | multiple status=1 same pair → median |
| Wrong/failed refresh | `is_not_update=1`, group `last_errors` |

## Standing gaps

- TONUSDT (Binance BREAK)
- Large NO_BASELINE from unsupported assets / uncovered destinations (do not mass-quarantine)
