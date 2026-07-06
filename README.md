# HealthySpine Livechat

Plain operator live chat for [HealthySpineDoc](https://healthyspinedoc.com/) — visitor widget, Laravel API, Telegram operator bridge. **No AI.**

## Branch

`hs-lc-f-no-ai-plain-operator-chat`

## Requirements

- PHP 8.3+
- MySQL/MariaDB
- Composer 2.x
- Queue worker (database driver supported)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan queue:work
php artisan serve
```

Health check: `GET /healthz`

## Widget embed

```html
<script
  src="https://YOUR-LIVECHAT-HOST/support-chat/widget.js"
  defer
  data-chat-enabled="1"
  data-api-base="https://YOUR-LIVECHAT-HOST"
></script>
```

Optional: `data-telegram-url`, `data-whatsapp-url`, `data-poll-ms`

## API routes

| Route | Purpose |
|-------|---------|
| `POST /client-api/v1/support/conversations` | Start conversation |
| `POST /client-api/v1/support/conversations/{uuid}/messages` | Send message |
| `GET /client-api/v1/support/conversations/{uuid}/messages` | Poll messages |
| `POST /callbacks/v1/support-chat/telegram` | Telegram operator webhook |

## Flow

1. Visitor sends message via widget → stored in DB → queued to Telegram
2. Operator replies in Telegram (reply/thread) → webhook → stored → visitor polls widget

No automated drafts, auto-replies, or assistant behavior.

## License

MIT
