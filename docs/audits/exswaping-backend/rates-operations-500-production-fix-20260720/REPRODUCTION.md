# REPRODUCTION

## Public HTTPS (apex `exswaping.com`)

Pre-fix (investigation window ~20:31–20:32 +0200):

| Pair | Status | Body (redacted) |
|------|--------|-----------------|
| `USDTTRC20/SBERRUB` | **500** | `{"message":"Server Error"}` |
| `BTC/USDTTRC20` | **500** | `{"message":"Server Error"}` |
| `USDTTRC20/SBPRUB` (dir 268) | **404** | canonical JSON API errors |
| `NOTACOIN/FAKE` | **404** | canonical JSON API errors |

Pattern: **found/active directions → 500**; missing/blocked → safe 404.

Also reproduced against previous frontend release `99bda19` and preview (same API host) → frontend not causal.

## Post-fix (after `c2c3e14` + FPM reload)

| Pair | Status |
|------|--------|
| `USDTTRC20/SBERRUB` | **200** + course.rate + currency icons |
| `BTC/USDTTRC20` | **200** |
| `ETH/USDTTRC20` | **200** |
| `USDTTRC20/CARDUAH` | **200** |
| `USDTTRC20/SBPRUB` | **404** |
| `NOTACOIN/FAKE` | **404** |

## Example command

```bash
curl -sS -D /tmp/ops.headers \
  'https://exswaping.com/apis/client-api/v1/rates/operations/USDTTRC20/SBERRUB' \
  -o /tmp/ops.body
```
