# EXSWAPING — CLEAN BESTCHANGE RUB EXPORT

UTC freeze: `2026-07-21T00:03:35Z`  
Release branch: `release/exswaping-bestchange-rub-reactivation-20260721`  
Certified code tip: `fe4c6dee5cc6edfef70888a7f55f6b6624cb6d80`

## Verdict

`EXSWAPING_BESTCHANGE_RUB_REACTIVATION_READY`

## Result

- Coin→RUB public rows: `101 → 69`
- Unsupported `NO_BASELINE` RUB rows: `32 → 0`
- CARDAMD public rows: `71 → 13`
- CARDAMD duplicate public rows removed: `58`
- Certified BestChange-ready RUB directions: `69`
- Exported classifications: `PASS=17`, `PASS_EXPLAINED_SPREAD=52`
- Exported `NO_POLICY`, `REVIEW`, `QUARANTINE_REQUIRED`: `0`
- Zero-reserve exported RUB rows: `0`
- Surface mismatches: `0`

## RUB bypass

The previous decision first resolved the source through a fixed asset allowlist. A null source identity skipped `RubFamilyPremiumPolicy::evaluateCoinRub()` and returned export allowed. The canonical evaluator now binds policy by RUB destination. Unknown source, missing baseline, stale baseline, missing policy, review, and quarantine outcomes fail closed across quote, order, XML, and BestChange.

## Unsupported inventory

The exact 32-row set is in `RUB_UNSUPPORTED_INVENTORY_BEFORE.json`: DASH 7, XMR 4, XRP 4, DOGE 1, BCH 3, ETC 6, SOL 1, CASHUSD 2, and ZELLEUSD 4. All are retained as internal configuration only and remain blocked until a separately approved trusted baseline exists. No new provider or guessed baseline was added.

## CARDAMD

Eight bank-specific currency records (IDs `52–58, 71`) remain intact. Public code `CARDAMD` is verified. The 13 public pairs use the explicit, versioned direction selection in `resources/rates/bestchange-public-directions.json`, chosen by historical usage with deterministic immutable-ID ties. Unconfigured CARDAMD pairs fail closed. No currency, direction, mapping, reserve, or historical-order row was changed.

## Delivery and safety

- XML delivery: healthy, `740` items.
- Two explicit generation cycles completed with stable item count.
- Sequential public delivery: `20/20`, no timeout, no zero-byte response.
- Native TON, unverified Payeer, quarantined, and deprecated routes are absent.
- Production files match release tip: drift `0`, missing `0`.
- Health critical: `false`; runtime drift: `0`.
- No funds moved, no real order created, no secret exposed, no mapping guessed, no force-push, and no BestChange contact.

## Submission files

- `BESTCHANGE_RUB_REACTIVATION_FINAL.json`
- `BESTCHANGE_RUB_REACTIVATION_FINAL.csv`
- `BESTCHANGE_RUB_REACTIVATION_MESSAGE_RU.txt`
- `BESTCHANGE_RUB_EXPORT_DIFF.json`

The message is for manual submission only.

