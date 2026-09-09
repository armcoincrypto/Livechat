# Pass B — main-only payment commits

Canonical tree = live tip `eee0bb2` (production-certified). Main tip after reconcile is a merge that **keeps the live tree** and records divergent main ancestry (`git merge -s ours`).

| COMMIT | INTENT | DECISION | WHY |
|---|---|---|---|
| 9cbe4d9 | Kobbopay USDT + Zelle gate | ALREADY_SUPERSEDED | Live recovery overlays present; live FS parity |
| 47deca7 | Track PaymentDestinationRouter | ALREADY_SUPERSEDED | Live router matches FS; main tip differed |
| 7bb7433 | Wave5 docs | EXCLUDE_PENDING_SEPARATE_REVIEW | Docs-only |
| 07ab31f | Track production Laravel source | ALREADY_SUPERSEDED | Superseded by live recovery series |
| eebe75e | liveOrders/admin/webhook harden | EXCLUDE_PENDING_SEPARATE_REVIEW | Not on live tip |
| bb7dc66 | fail-closed no deposit dest | ALREADY_SUPERSEDED | Live destination guard recovery |
| 4b37cac | create idempotency tests | EXCLUDE_PENDING_SEPARATE_REVIEW | Test-only follow-up |
| e8af8d3 | PAID writes transactional | EXCLUDE_PENDING_SEPARATE_REVIEW | Live Wave A is certified authority |
| 9168e0f | Telegram fail-open | ALREADY_SUPERSEDED | Live Telegram recovery |
| 9d6043f | WAITING_HANDLE→PAID repair | ALREADY_SUPERSEDED | Live funded-repair + Wave A |
| 88edb0d | funded reconciliation | ALREADY_SUPERSEDED | Live `ccc435d` overlay |
| 8f1f364 | manual completion lock | ALREADY_SUPERSEDED | Live manual-completion recovery |

PAYMENT_COMMITS_INCLUDED=none  
PAYMENT_COMMITS_EXCLUDED=all twelve
