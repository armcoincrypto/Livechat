# Reserve policy — 2026-07-20T09:30:01Z

## Canonical reserve source (export / order)
- `type_reserve = 0` → destination currency effective reserve via `ReserveLinkResolver::getEffectiveSumma`
- `type_reserve = 1` → `direction_reserve`
- XML `<amount>` is derived from the same export-path reserve logic

## Surfaces
| Surface | Rule |
|---|---|
| Quote | active + rate quarantine + not deprecated |
| Order | quote + positive effective reserve |
| BestChange XML export | export filters + VERIFIED mapping; amount from currency reserve |
| Website display | may use max_display_reserve / shared reserve groups |

## Fix applied
`rates:direction-status` now resolves `currency2_effective_reserve` (was under-reporting LTC as reserve_missing while XML showed 100000000).

## Remaining inconsistencies
- `direction_reserve` column often `0` even when currency reserve is healthy — operators should read `reserve_source`, not the raw column alone.
- `reserve_max_limit` is optional capacity metadata; not required when currency reserve is positive.
