# POLICY_PARITY_CHECK

## RUB / direction 268

| Surface | Result |
|---------|--------|
| HTTP operations `USDTTRC20/SBPRUB` | 404 (not found / inactive) — no 500 |
| `evaluateDirection(268)` | quote=0 order=0 export=0 BestChange=0 |
| Quarantine / RUB family reasons | Present (`rub_family_quarantine_required`, etc.) |

## Supported non-RUB / stablecoin

| Pair | quote | order | export | BestChange |
|------|-------|-------|--------|------------|
| USDTTRC20→SBERRUB | 1 | 1 | 1 | 1 |
| BTC→USDTTRC20 | 1 | 1 | 1 | 1 |

## Tests (php8.4 PHPUnit, Rates + policy)

```text
tests/Unit/Rates/                         → 78 tests OK
RubFamilyPremiumPolicyTest + RatePublicSurfaceGateTest + UpdateSitemapCommandTest
                                          → 31 tests OK (3458 assertions)
RateDirectionEligibilityCurrencyLoadTest  → 2 tests OK
```

## Sitemap / BestChange

No policy code paths weakened. Fix only restores full currency attribute load for serialization. INDEX-tier sitemap drop (`14f81e0`) remains on history. Dir 268 remains ineligible.
