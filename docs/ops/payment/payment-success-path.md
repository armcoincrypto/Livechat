# Payment success → PAID path

## Canonical flow (after Wave 3)

1. `merchant:webhook` (`WebhookMerchantCommand`) or HTTP callback (`CallbackPaymentHandlingTrait`)
2. `SupportsCheckPayment::checkInPayment()` observes provider success (`CONFIRMATIONS_DONE`)
3. Amount/currency gates: mismatch → structured warning, **no PAID**, **no PAYMENT_SUCCESS(9)**
4. `ConfirmedInboundPaidTransition::apply()`: DB transaction, `lockForUpdate()`, unique txid, `wallet_transactions` updateOrCreate (no duplicate create)
5. Re-read status; if already 7 → `already_paid` noop (no extra status log, no PAYMENT_SUCCESS spam)
6. If from ∈ {3,12,13}: `grant` permit + `setStatus(7)`
7. Verify status persisted 7
8. Log structured `order_marked_paid`
9. `logPaymentStatusChange(PAYMENT_SUCCESS)` **only after PAID** — meaning **ORDER_MARKED_PAID**

## Task 2250 historic class

**STATUS_GATE_BUG** + **LOG_BEFORE_COMMIT**

`PAYMENT_SUCCESS` was logged after a skipped `setStatus` (`in_array($this->getStatus(), [3,12,13], true)`). Status history never 3→7; `started_at` null. 117 PAYMENT_SUCCESS rows coexisted with PAID=0. Later timeout set `is_bot=0`. Exact in-memory getStatus() value in January cannot be reconstructed from remaining artifacts (current PDO returns int). Telegram-after-wallet remains a plausible contributing exception on the *first* poll only; it cannot explain 117 subsequent PAYMENT_SUCCESS without a skipped gate.

## PAYMENT_SUCCESS meaning

- Before: payment observed **or** (incorrectly) implied PAID.
- After: **ORDER_MARKED_PAID** only. Detection remains `CONFIRMATIONS_DONE` / wallet txid. Structured `order_marked_paid` vs `payment_detected_transition_failed`.
