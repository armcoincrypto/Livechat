<?php

declare(strict_types=1);

/**
 * Dedicated technical/ops Telegram routing.
 * NEVER fall back to order-operator DMs or support/traffic chats.
 */
return [
    /*
    | Dedicated private ops group/channel chat id.
    | Required before any technical Telegram delivery.
    */
    'chat_id' => env('OPS_TELEGRAM_CHAT_ID', env('TELEGRAM_OPS_CHAT_ID', '')),

    /*
    | Master switch for rate-authority / infra Telegram delivery.
    | Default OFF — JSONL + application log remain the primary path.
    */
    'rate_alerts_enabled' => filter_var(
        env('RATE_AUTHORITY_DRIFT_TELEGRAM_ALERTS', env('TELEGRAM_OPS_ALERTS_ENABLED', '0')),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Reuse the operator bot token resolver when true (no second hardcoded token).
    | Ops chat must add @exswapingnotyfbot (or the configured operator bot) as member/admin.
    */
    'reuse_operator_bot_token' => filter_var(
        env('TELEGRAM_OPS_REUSE_OPERATOR_BOT', '1'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Severities eligible for Telegram. WATCH stays JSONL-only.
    */
    'telegram_severities' => ['CRITICAL', 'WARNING'],

    /*
    | WATCH / non-customer-impact parser thin-book states: never Telegram.
    */
    'suppress_watch_telegram' => true,
];
