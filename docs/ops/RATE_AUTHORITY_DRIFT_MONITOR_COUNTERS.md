# Rate authority drift monitor — counter definitions

Observability only. No pricing or ownership mutation.

## Ownership populations (do not equate)

| Key | Definition | Use |
| --- | --- | --- |
| `OWNERSHIP_ACTIVE_NONDELETED` | `direction_exchange.status=1 AND deleted_at IS NULL` | **Canonical** operator / freeze baseline for the monitor |
| `OWNERSHIP_STATUS1_INCLUDING_SOFT_DELETED` | `status=1` (includes soft-deleted) | Historical companion only |

`OWNERSHIP_COUNT_DEFINITION=ACTIVE_NONDELETED`  
`OWNERSHIP_COUNT_DEFINITION_DOCUMENTED=YES`

Soft-deleted rows with `status=1` explain prior 2055 vs current 2048 BestChange counts.

## BestChange failure classes (independent)

| Counter | Meaning |
| --- | --- |
| `BESTCHANGE_BOOK_STALE_TOTAL` | Ingest heartbeat age exceeds freshness policy (1800s critical) |
| `BESTCHANGE_PARSER_ERROR_TOTAL` | `is_error_parser=1` (any parser failure subtype) |
| `BESTCHANGE_POSITION_INSUFFICIENT_DEPTH_TOTAL` | Parser error with `explain_payload.reason=position_insufficient_depth` |
| `BESTCHANGE_STALE_TOTAL` | **Alias of book-age only** (not parser errors) |

A direction may contribute to more than one fact counter. Alert `root_cause` uses the first meaningful stage (link → parser/depth → book age → economic drift).

## Telegram

Rate-health alerts must not use the traffic/customer Telegram chat.  
Required owner input: dedicated `OPS_TELEGRAM_CHAT_ID` (or approved ops destination). Until set: JSONL + application logs only.
