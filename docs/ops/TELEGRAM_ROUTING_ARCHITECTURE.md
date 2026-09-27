# Telegram routing architecture

## Destinations (live inventory)

| Classification | Bot | Destination | Purpose |
| --- | --- | --- | --- |
| ORDER_OPERATOR_CHAT | `@exswapingnotyfbot` | Operator DMs (`TELEGRAM_OPERATOR_LINKS`) | Order cards + PAID attention alerts |
| TRAFFIC_CHAT | `@exswapinglivebot` | Support forum group | SupportChat visitor ↔ operator topics + hourly traffic report |
| ADMIN_CHAT / CHANNEL | legacy `telegram_notifications` channel | Channel id (non–order-card) | Withdrawal / KYC card / admin events (not order lifecycle cards) |
| OPS_TECHNICAL_CHAT | same operator bot (reuse) | `OPS_TELEGRAM_CHAT_ID` | Rate / infra alerts — **MISSING until owner provides** |

## Order path

`OrderPayController::confirm` → status 3 → `iEXApp::telegramNotificationForChannel('new_order_for_operator')` → `TelegramOrderJob` (unique) → `TelegramOrderLifecyclePublisher::publishPaid` → EDIT main card + exactly-one PAID attention alert.

Dedup: `order_id + PAID_ATTENTION` via cache + `opt_params.telegram_operator_card.paid_attention_alert_sent` + mutex.

## Ops path

`RateAuthorityDriftMonitor` → JSONL/Log always → `TelegramOpsAlertRouter` only when enabled + dedicated chat set. Never support group / operator DM.
