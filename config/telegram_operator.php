<?php

declare(strict_types=1);

return [
    /*
    | Telegram operator order controls (Wave 1).
    | Default OFF — notifications stay outbound-only until explicitly enabled.
    */
    'actions_enabled' => filter_var(env('TELEGRAM_OPERATOR_ACTIONS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),

    /*
    | When true: auth/claim/evidence/guards run, but Transaction::success is NOT invoked.
    */
    'dry_run' => filter_var(env('TELEGRAM_OPERATOR_ACTIONS_DRY_RUN', '1'), FILTER_VALIDATE_BOOLEAN),

    /*
    | Webhook secret for X-Telegram-Bot-Api-Secret-Token (or query token).
    */
    'webhook_secret' => env('TELEGRAM_OPERATOR_WEBHOOK_SECRET', ''),

    /*
    | Optional explicit bot token. Empty → resolve from telegram_notifications.
    */
    'bot_token' => env('TELEGRAM_OPERATOR_BOT_TOKEN', ''),

    /*
    | Map telegram_user_id => admin users.id
    | Format: "123456789:5,987654321:119"
    | Empty map = fail closed (no operator actions).
    */
    'operator_links' => env('TELEGRAM_OPERATOR_LINKS', ''),

    /*
    | Permission required for complete (reuse existing Spatie permission).
    */
    'complete_permission' => 'admin_orders_execute',

    /*
    | Claim permission — same as complete for Wave 1 simplicity.
    */
    'claim_permission' => 'admin_orders_execute',

    'admin_order_path' => env('TELEGRAM_OPERATOR_ADMIN_ORDER_PATH', '/iexadmin/#/orders/'),

    'pending_ttl_minutes' => 30,

    /*
    | Wave 3: also send the canonical TelegramNewOrder (same keyboard) to
    | authorized linked operator private chats (TELEGRAM_OPERATOR_LINKS).
    | Channel delivery via telegram_notifications is unchanged.
    */
    'notify_operator_dms' => filter_var(env('TELEGRAM_OPERATOR_NOTIFY_DMS', '1'), FILTER_VALIDATE_BOOLEAN),
];
