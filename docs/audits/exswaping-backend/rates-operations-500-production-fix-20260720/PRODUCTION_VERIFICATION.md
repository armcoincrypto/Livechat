# PRODUCTION_VERIFICATION

## Endpoint matrix (post FPM reload)

| Case | URL | HTTP | Notes |
|------|-----|------|-------|
| Supported (failing before) | `/rates/operations/USDTTRC20/SBERRUB` | **200** | course.rate present; buy/sell payment icons |
| Supported crypto | `/rates/operations/BTC/USDTTRC20` | **200** | |
| Blocked RUB / dir 268 | `/rates/operations/USDTTRC20/SBPRUB` | **404** | canonical errors JSON |
| Unsupported | `/rates/operations/NOTACOIN/FAKE` | **404** | |
| Malformed | `/rates/operations/bad!!/xx` | **200** body `{"code":-1,"message":"Not Found"}` | pre-existing edge; **not** 500 |

## Logs

No new `rate_public_surface_quote_gate_failed` / undefined `evaluateDirection` after fix window (~21:44+).

## Frontend recovery

| Check | Result |
|-------|--------|
| Public `/en/` | 200, `data-exs-visual-redesign="20260720"` |
| Release SHA | still `7414aef` via PM2 cwd |
| Ops API used by calculator | 200 with course + currencies |
| Receive amount path | Calculator can compute from restored `attributes.course.rate` + limits (dry-run only; no order) |

## Runtime

PHP-FPM ready; Horizon/queue processes present; no reload loop.
