# Operator handoff — CoinMarketCap, TON, Payeer/BestChange mappings

Generated for release `release/exswaping-rate-pipeline-certified-20260719`.
Do not paste API keys into chat or commit secrets.

## CoinMarketCap (`PLAN_LIMIT_EXCEEDED`)

Status: group disabled in production after monthly credit limit (provider code 1010 / HTTP 429).

Checklist:
1. Log in to the CoinMarketCap developer account that owns the production keys.
2. Verify account ownership and current plan / monthly credit usage.
3. Renew, upgrade, or replace the API key in the production secret store (`parser_api_keys` / ops vault). Do not paste the key into chat.
4. Run a dry provider probe (no production rate apply):

```bash
sudo -u app_exswapin_usr php8.4 artisan rates:provider-probe --provider=coinmarketcap --symbols=BTC,ETH --format=json
```

If `rates:provider-probe` is not yet deployed, use the controlled compile only after a successful authenticated probe script approved by ops.
5. Confirm HTTP success, provider `error_code=0`, valid JSON, and fresh timestamps.
6. Re-enable `group_parser_exchange` alias `coinmarketcap` only after success.
7. Verify CMC quotes agree with Binance/WhiteBit within the 2% provider divergence policy.

CMC is optional while Binance and WhiteBit remain healthy.

## TON (`TON_BASELINE_UNAVAILABLE_KEEP_QUARANTINED`)

| Provider | Official API | Auth | TON/USDT | Fresh? | Existing integration | Effort | Risk |
|---|---|---|---|---|---|---|---|
| Binance | Yes | No (public) | Symbol exists but market `BREAK` | No | Yes | Low | High — frozen/zero book |
| WhiteBit | Yes | No (public) | No TON markets observed | N/A | Yes | Low | N/A |
| Rapira | Yes | No (public) | Was configured | Stale since 2026-04-27 | Yes (disabled) | Low | High — stale |
| CoinMarketCap | Yes | API key | Would include TON if plan works | No (plan exhausted) | Yes (disabled) | Low | Plan/billing |
| New official venue | TBD | TBD | TBD | TBD | No | Medium–High | Must be approved |

Operator decision (pick one):
- Approve an existing integrated provider once it returns a fresh TON/USDT quote.
- Approve a new official provider (implementation required before restore).
- Keep all TON-derived directions quarantined.

Until approved: Rapira stays disabled; BestChange ID 209 remains GRAM; no TON remapping; no TON restores.

## Mapping decisions (no auto-remap)

| Local code | Live BestChange | Status | Recommended action |
|---|---|---|---|
| PRUSD | absent (ID 108 is CARDVND) | DRIFTED | OPERATOR_BUSINESS_REVIEW / CREATE_NEW_VERIFIED_MAPPING or DEPRECATE_LOCAL_CURRENCY |
| PREUR | absent | DRIFTED | same |
| PRRUB | absent | DRIFTED | same |
| CARDVND | ID 108 `[CARDVND]` | VERIFIED | KEEP mapping; fix local codes.json drift note |
| TON | no `[TON]` in live catalog | DRIFTED | KEEP_QUARANTINED until source + catalog decision |
| GRAM | ID 209 `[GRAM]` | VERIFIED | Do not treat as TON |
| BNB family | use BNBBEP20 etc. | AMBIGUOUS for bare `BNB` | OPERATOR_BUSINESS_REVIEW for export codes |

Use `php8.4 artisan rates:quarantine-triage` and `storage/app/bestchange_mapping_verification.json` for current counts. Do not guess IDs.
