# PAID order operations (short SOP)

Canonical copy. Evidence also under `/var/lib/server-ops/audits/exswaping-funded-recon-*/ops/`.

1. Identify confirmed funded orders with `php8.4 artisan orders:funded-health` / `orders:reconcile-funded` (txid-bearing `wallet_transactions` only).
2. Verify destination on authorized order detail — not in list dumps.
3. Perform external/manual settlement. Autopay stays OFF.
4. Capture settlement reference/note (`settlement_reference` or `extra_fields` or `message_success`).
5. Complete via `POST /iexadmin/frontend-api/vue/orderHandler/{id}` `action=success`.
6. Verify COMPLETED and a single status-log transition to 4.
7. Do not retry uncertain outbound blindly.
8. Escalate txid-on-WAITING_HANDLE, mismatches, late payments, ambiguous cases.

Do not bulk-complete old PAID backlog.
Do not auto-promote WAITING_HANDLE → PAID.
