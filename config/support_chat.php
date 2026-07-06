<?php

declare(strict_types=1);

return [
    /*
     * Use FILTER_VALIDATE_BOOLEAN so .env values like true/false/1/0/on/off are predictable.
     * Default when the key is omitted: treat as disabled (use string that parses to false).
     */
    'enabled' => filter_var(env('SUPPORT_CHAT_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),

    /*
     * Prefer SUPPORT_CHAT_MAX_MESSAGE_LENGTH; legacy SUPPORT_MESSAGE_MAX_LENGTH remains as fallback.
     */
    'message_max_length' => (int) env(
        'SUPPORT_CHAT_MAX_MESSAGE_LENGTH',
        env('SUPPORT_MESSAGE_MAX_LENGTH', 8000)
    ),

    /*
     * P4.1: visitor-facing send/upload hints + approximate operator-message receipt (visitor poll).
     * No websocket; timestamps update when the visitor successfully polls GET /messages.
     */
    'message_states_enabled' => filter_var(env('SUPPORT_CHAT_MESSAGE_STATES_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),

    'poll' => [
        'default_limit' => (int) env('SUPPORT_POLL_DEFAULT_LIMIT', 50),
        'max_limit' => (int) env('SUPPORT_POLL_MAX_LIMIT', 100),
    ],

    'access_token' => [
        'length' => (int) env('SUPPORT_ACCESS_TOKEN_LENGTH', 64),
    ],

    /*
     * Laravel throttle names: support-create (per IP), support-send / support-poll (per bearer token hash).
     * Prefer SUPPORT_CHAT_* env keys; legacy SUPPORT_RATE_* keys remain as fallback.
     */
    'rate_limits' => [
        'create' => [
            'per_hour' => (int) env(
                'SUPPORT_CHAT_CREATE_PER_IP_PER_HOUR',
                env('SUPPORT_RATE_CREATE_PER_HOUR', 20)
            ),
            'per_minute' => (int) env(
                'SUPPORT_CHAT_CREATE_PER_IP_PER_MINUTE',
                env('SUPPORT_RATE_CREATE_PER_MINUTE', 5)
            ),
        ],
        'send' => [
            'per_minute' => (int) env(
                'SUPPORT_CHAT_SEND_PER_CONVERSATION_PER_MINUTE',
                env('SUPPORT_RATE_SEND_PER_MINUTE', 30)
            ),
        ],
        'poll' => [
            'per_minute' => (int) env(
                'SUPPORT_CHAT_POLL_PER_CONVERSATION_PER_MINUTE',
                env('SUPPORT_RATE_POLL_PER_MINUTE', 120)
            ),
        ],
    ],

    /*
     * Application-level spam guards (Phase 1). Master switch disables all new checks for instant rollback.
     */
    'spam' => [
        'hardening_enabled' => filter_var(env('SUPPORT_CHAT_SPAM_HARDENING_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        'duplicate_cooldown_enabled' => filter_var(env('SUPPORT_CHAT_DUPLICATE_COOLDOWN_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        'repeated_character_guard_enabled' => filter_var(env('SUPPORT_CHAT_REPEATED_CHARACTER_GUARD_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        'duplicate_cooldown_seconds' => max(5, min(120, (int) env('SUPPORT_CHAT_DUPLICATE_COOLDOWN_SECONDS', 15))),
        'uniform_single_char_min_length' => max(16, min(500, (int) env('SUPPORT_CHAT_SPAM_UNIFORM_SINGLE_CHAR_MIN_LENGTH', 64))),
        'max_repeated_char_run' => max(80, min(2000, (int) env('SUPPORT_CHAT_SPAM_MAX_REPEATED_CHAR_RUN', 200))),
        'repeated_char_message_min_length' => max(40, min(500, (int) env('SUPPORT_CHAT_SPAM_REPEATED_CHAR_MESSAGE_MIN_LENGTH', 120))),
    ],

    /*
     * Visitor attachments (P2.1): private disk only; disabled by default.
     */
    'attachments' => [
        'enabled' => filter_var(env('SUPPORT_CHAT_ATTACHMENTS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
        'max_image_mb' => max(1, min(50, (int) env('SUPPORT_CHAT_ATTACHMENTS_MAX_IMAGE_MB', 5))),
        'max_video_mb' => max(1, min(200, (int) env('SUPPORT_CHAT_ATTACHMENTS_MAX_VIDEO_MB', 25))),
        'max_pdf_mb' => max(1, min(100, (int) env('SUPPORT_CHAT_ATTACHMENTS_MAX_PDF_MB', 10))),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'video/mp4',
            'application/pdf',
        ],
        'disk' => env('SUPPORT_CHAT_ATTACHMENTS_DISK', 'support_chat_private'),
        'path_prefix' => env('SUPPORT_CHAT_ATTACHMENTS_PATH_PREFIX', 'support-chat/attachments'),

        /*
         * P2.2: forward visitor uploads to Telegram forum topic (images + PDF only by default).
         */
        'telegram_enabled' => filter_var(env('SUPPORT_CHAT_ATTACHMENTS_TELEGRAM_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
        'telegram_images_enabled' => filter_var(env('SUPPORT_CHAT_ATTACHMENTS_TELEGRAM_IMAGES_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        'telegram_documents_enabled' => filter_var(env('SUPPORT_CHAT_ATTACHMENTS_TELEGRAM_DOCUMENTS_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        'telegram_video_enabled' => filter_var(env('SUPPORT_CHAT_ATTACHMENTS_TELEGRAM_VIDEO_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Dedicated support bot + operators group.
     * When disabled or misconfigured, visitor APIs still succeed; jobs are not dispatched from SupportChatService.
     */
    'telegram' => [
        'enabled' => filter_var(env('SUPPORT_TELEGRAM_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
        'bot_token' => env('SUPPORT_TELEGRAM_BOT_TOKEN', ''),
        'group_id' => env('SUPPORT_TELEGRAM_GROUP_ID'),
        /*
         * Must match the secret_token passed to Telegram setWebhook. Telegram sends it back as
         * X-Telegram-Bot-Api-Secret-Token — required for the inbound webhook when support + telegram are enabled.
         */
        'webhook_secret' => env('SUPPORT_TELEGRAM_WEBHOOK_SECRET', ''),

        /*
         * When true: create one Telegram forum topic per support conversation (requires forum supergroup
         * and bot admin rights including can_manage_topics). When false: legacy flat-group + reply-chain only.
         */
        'use_forum_topics' => filter_var(env('SUPPORT_TELEGRAM_USE_FORUM_TOPICS', '0'), FILTER_VALIDATE_BOOLEAN),

        /*
         * Slash commands in mapped forum topics (/status, /close, /reopen) for group admins/creators only.
         * Command text is never stored as operator messages for the website visitor.
         */
        'operator_commands_enabled' => filter_var(env('SUPPORT_TELEGRAM_OPERATOR_COMMANDS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),

        /*
         * When true: Telegram /close and /reopen operator commands also call closeForumTopic / reopenForumTopic
         * (requires forum topics). Admin UI / CLI close do not auto-close Telegram topics in this phase.
         */
        'auto_close_topics' => filter_var(env('SUPPORT_TELEGRAM_AUTO_CLOSE_TOPICS', '0'), FILTER_VALIDATE_BOOLEAN),

        /*
         * P2.4: store operator photo/document from Telegram forum topic for visitor widget (admin/creator only).
         */
        'inbound_attachments_enabled' => filter_var(env('SUPPORT_TELEGRAM_INBOUND_ATTACHMENTS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Public site widget (static JS under public/support-chat/widget.js).
     */
    'widget' => [
        'poll_interval_ms' => (int) env('SUPPORT_CHAT_WIDGET_POLL_MS', 4000),
    ],

    'diagnostics' => [
        'enabled' => filter_var(env('SUPPORT_CHAT_DIAGNOSTICS_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
    ],
];
