# TON decision — 2026-07-20T09:30:01Z

## Verdict
`KEEP_TON_DISABLED` + `REMOVE_UNSAFE_TON_EXPORTS` (via mapping fail-closed)

## Directions 1765 / 1778
| ID | Pair | Active | Export eligible | In public XML | Mapping |
|---|---|---|---|---|---|
| 1765 | TON→CARDTJS | yes | **no** | **no** | TON=ABSENT |
| 1778 | TON→UZCUZS | yes | **no** | **no** | TON=ABSENT |

## GRAM separation
- BestChange ID **209 = GRAM** (VERIFIED), never TON.
- Export filter requires VERIFIED mapping; TON blocked.

## Provider
- Rapira TON source remains disabled/stale.
- No new TON provider approved.
- Decision: do **not** integrate a new provider without operator approval.

## Mechanism
`ExportFormatHelpers::isExportMappingAllowed()` — only VERIFIED codes export.
