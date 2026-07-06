# HealthySpine Livechat

Standalone Laravel backend for the HealthySpineDoc visitor support chat widget and Telegram operator workflow.

Reconstructed from the Exswaping `SupportChat` package (`armcoincrypto/Livechat`).

## Status

- **HS-LC-C2:** SupportChat core source reconstructed
- **HS-LC-C3:** Standalone Laravel 12 skeleton (this branch)
- **HS-LC-D (next):** Clinic adaptation — branding, playbook, disable exchange lookups

## Requirements

- PHP 8.3+ (Ubuntu 24.04 compatible)
- MySQL/MariaDB
- Composer 2.x
- Redis optional (Predis included for traffic analytics when enabled)

## Quick start (development)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Health check: `GET /healthz` → `{"status":"ok","service":"healthyspine-livechat"}`

## SupportChat API routes

Registered by `iEXPackages\SupportChat\SupportChatServiceProvider`:

| Route | Purpose |
|-------|---------|
| `POST /client-api/v1/support/conversations` | Start visitor conversation |
| `POST /client-api/v1/support/conversations/{uuid}/messages` | Send message |
| `GET /client-api/v1/support/conversations/{uuid}/messages` | Poll messages |
| `POST /callbacks/v1/support-chat/telegram` | Telegram operator webhook |

Widget asset: `public/support-chat/widget.js`

## HS-LC-D adaptation notes (not done yet)

The following Exswaping-specific logic remains in source and should be disabled or replaced in HS-LC-D:

- `SupportAiOrderContextService` — exchange order lookup (`App\Models\Task`)
- `SupportAiDirectionContextService` — exchange direction lookup (`Currency`, `DirectionExchange`)
- Exchange playbook / intent patterns in `packages/SupportChat/Data/`
- Widget branding (`exswaping` storage keys, titles, Telegram link)
- `TrafficHourlyAnalyticsService` / `TrafficHourlyReportService` — exchange traffic reports

Placeholder stubs (HS-LC-C3):

- `packages/GeoIp/` — returns unknown visitor country
- `packages/WorkStatus/` — always-online stub for direction lookup

## License

MIT
