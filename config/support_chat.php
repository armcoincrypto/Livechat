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
     * Dedicated support bot + operators group (separate from order notify bot).
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

        /*
         * LC-P10: privacy-safe hourly aggregate report (support chat scope only).
         * Reuses bot_token; target chat/channel via HOURLY_REPORT_CHAT_ID (e.g. @exswapinglivebot).
         */
        'hourly_report' => [
            'enabled' => filter_var(env('SUPPORT_TELEGRAM_HOURLY_REPORT_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
            'chat_id' => env('SUPPORT_TELEGRAM_HOURLY_REPORT_CHAT_ID', '@exswapinglivebot'),
        ],
    ],

    /*
     * Public site widget (static JS under public/support-chat/widget.js).
     * Script tag gates on data-chat-enabled="1"; backend still requires SUPPORT_CHAT_ENABLED for API routes.
     */
    'widget' => [
        'poll_interval_ms' => (int) env('SUPPORT_CHAT_WIDGET_POLL_MS', 4000),
    ],

    /*
     * P4.2: structured logs + admin health/diagnostics (internal only).
     */
    'diagnostics' => [
        'enabled' => filter_var(env('SUPPORT_CHAT_DIAGNOSTICS_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * AI-SUPPORT-1: operator-assist draft replies (OpenAI). Default disabled.
     * Never auto-sends to visitors — drafts appear in Telegram for operator review only.
     */
    'ai' => [
        'enabled' => filter_var(env('SUPPORT_AI_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('SUPPORT_AI_PROVIDER', 'openai'),
        'model' => env('OPENAI_SUPPORT_MODEL', 'gpt-4o-mini'),
        'openai_api_key' => env('OPENAI_API_KEY', ''),
        'timeout_seconds' => max(5, min(60, (int) env('SUPPORT_AI_TIMEOUT_SECONDS', 15))),
        'max_context_messages' => max(2, min(20, (int) env('SUPPORT_AI_MAX_CONTEXT_MESSAGES', 8))),
        'operator_assist_only' => filter_var(env('SUPPORT_AI_OPERATOR_ASSIST_ONLY', '1'), FILTER_VALIDATE_BOOLEAN),
        'telegram_preview_enabled' => filter_var(
            env('SUPPORT_AI_TELEGRAM_PREVIEW_ENABLED', env('SUPPORT_AI_ENABLED', '0')),
            FILTER_VALIDATE_BOOLEAN
        ),
        'telegram_choices' => max(1, min(4, (int) env('SUPPORT_AI_TELEGRAM_CHOICES', 4))),
        'telegram_separate_message' => filter_var(
            env('SUPPORT_AI_TELEGRAM_SEPARATE_MESSAGE', env('SUPPORT_AI_ENABLED', '0')),
            FILTER_VALIDATE_BOOLEAN
        ),

        /*
         * Phase A.1: operator AI suggestion acceptance telemetry (deterministic, non-blocking).
         */
        'acceptance_tracking' => [
            'enabled' => filter_var(env('SUPPORT_AI_ACCEPTANCE_TRACKING_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'recent_window_minutes' => max(1, min(120, (int) env('SUPPORT_AI_ACCEPTANCE_RECENT_WINDOW_MINUTES', 15))),
            'exact_threshold' => max(0.9, min(1.0, (float) env('SUPPORT_AI_ACCEPTANCE_EXACT_THRESHOLD', 0.98))),
            'modified_threshold' => max(0.4, min(0.97, (float) env('SUPPORT_AI_ACCEPTANCE_MODIFIED_THRESHOLD', 0.60))),
            'min_meaningful_length' => max(8, min(80, (int) env('SUPPORT_AI_ACCEPTANCE_MIN_MEANINGFUL_LENGTH', 20))),
        ],

        /*
         * LC-H: operator AI draft usage monitoring (telemetry only; no AI behavior changes).
         */
        'usage_monitoring' => [
            'enabled' => filter_var(env('SUPPORT_AI_USAGE_MONITORING_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'record_in_console' => filter_var(env('SUPPORT_AI_USAGE_MONITOR_RECORD_IN_CONSOLE', '0'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * Phase A.2: conversation outcome telemetry for AI learning correlation.
         */
        'outcome_tracking' => [
            'enabled' => filter_var(env('SUPPORT_AI_OUTCOME_TRACKING_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * Phase A.3: matching diagnostics (read-only CLI reports).
         */
        'matching_diagnostics' => [
            'enabled' => filter_var(env('SUPPORT_AI_MATCHING_DIAGNOSTICS_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * Phase A.4: deterministic quarantine filter before candidate promotion.
         */
        'candidate_filtering' => [
            'enabled' => filter_var(env('SUPPORT_AI_CANDIDATE_FILTERING_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'block_ignored_usage' => filter_var(env('SUPPORT_AI_CANDIDATE_FILTER_BLOCK_IGNORED', '1'), FILTER_VALIDATE_BOOLEAN),
            'block_unknown_usage' => filter_var(env('SUPPORT_AI_CANDIDATE_FILTER_BLOCK_UNKNOWN', '1'), FILTER_VALIDATE_BOOLEAN),
            'block_failed_outcome' => filter_var(env('SUPPORT_AI_CANDIDATE_FILTER_BLOCK_FAILED', '1'), FILTER_VALIDATE_BOOLEAN),
            'block_reopened_outcome' => filter_var(env('SUPPORT_AI_CANDIDATE_FILTER_BLOCK_REOPENED', '1'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * Phase A.5: accepted-only promotion threshold (deterministic, non-blocking on generation).
         */
        'promotion_thresholds' => [
            'enabled' => filter_var(env('SUPPORT_AI_PROMOTION_THRESHOLDS_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'min_accepted_samples' => max(1, min(50, (int) env('SUPPORT_AI_PROMOTION_MIN_ACCEPTED_SAMPLES', 3))),
            'allow_unlinked_candidates' => filter_var(env('SUPPORT_AI_PROMOTION_ALLOW_UNLINKED', '0'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * Phase A wrap-up: weekly read-only audit (CLI + admin diagnostics widget).
         */
        'weekly_audit' => [
            'enabled' => filter_var(env('SUPPORT_AI_WEEKLY_AUDIT_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'lookback_days' => max(1, min(90, (int) env('SUPPORT_AI_WEEKLY_AUDIT_LOOKBACK_DAYS', 7))),
            'milestone_min_accepted' => max(1, (int) env('SUPPORT_AI_MILESTONE_MIN_ACCEPTED', 10)),
            'milestone_min_resolved' => max(1, (int) env('SUPPORT_AI_MILESTONE_MIN_RESOLVED', 10)),
        ],

        /*
         * AI-SUPPORT-AUTOLEARN-4: optional runtime overlay from staged/approved learning candidates.
         * Disabled by default — injects read-only context at draft time; never edits playbook files.
         */
        'learning_overlay' => [
            'enabled' => filter_var(env('SUPPORT_AI_LEARNING_OVERLAY_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
            'min_status' => env('SUPPORT_AI_LEARNING_OVERLAY_MIN_STATUS', 'approved'),
            'max_candidates' => max(1, min(50, (int) env('SUPPORT_AI_LEARNING_OVERLAY_MAX_CANDIDATES', 12))),
            'max_chars' => max(500, min(10000, (int) env('SUPPORT_AI_LEARNING_OVERLAY_MAX_CHARS', 3000))),
            'allowed_types' => array_values(array_filter(array_map(
                static fn (string $type): string => trim($type),
                explode(',', (string) env(
                    'SUPPORT_AI_LEARNING_OVERLAY_ALLOWED_TYPES',
                    'playbook_example,tone_rule,safety_rule,intent_rule,followup_rule,operator_action_rule,edge_case_rule'
                ))
            ))),
        ],

        /*
         * AI-SUPPORT-KNOWLEDGE-BASE-1B: operator-derived business knowledge for AI drafts.
         * Additive context only — never auto-sends, never overrides safety rules.
         */
        'knowledge' => [
            'enabled' => filter_var(env('SUPPORT_AI_KNOWLEDGE_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'max_rules' => max(1, min(10, (int) env('SUPPORT_AI_KNOWLEDGE_MAX_RULES', 5))),
            'max_chars' => max(500, min(5000, (int) env('SUPPORT_AI_KNOWLEDGE_MAX_CHARS', 2500))),
            'include_unvalidated' => filter_var(env('SUPPORT_AI_KNOWLEDGE_INCLUDE_UNVALIDATED', '0'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * AI-SUPPORT-TEMPLATE-LAYER-1: operator reply wording patterns for AI drafts.
         * Additive context only — never auto-sends, never overrides safety or knowledge rules.
         */
        'templates' => [
            'enabled' => filter_var(env('SUPPORT_AI_TEMPLATES_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'max' => max(1, min(10, (int) env('SUPPORT_AI_TEMPLATES_MAX', 3))),
            'max_chars' => max(300, min(5000, (int) env('SUPPORT_AI_TEMPLATES_MAX_CHARS', 1500))),
            'include_unvalidated' => filter_var(env('SUPPORT_AI_TEMPLATES_INCLUDE_UNVALIDATED', '0'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * AI-SUPPORT-UX-1: operator presentation layer (dynamic counts, dedup, compact Telegram).
         */
        'ux' => [
            'enabled' => filter_var(env('SUPPORT_AI_UX_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'dynamic_choices' => filter_var(env('SUPPORT_AI_UX_DYNAMIC_CHOICES', '1'), FILTER_VALIDATE_BOOLEAN),
            'dedup_enabled' => filter_var(env('SUPPORT_AI_UX_DEDUP_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'policy_protection' => filter_var(env('SUPPORT_AI_UX_POLICY_PROTECTION', '1'), FILTER_VALIDATE_BOOLEAN),
        ],

        /*
         * AI-SUPPORT-UI-2: optional debug metadata in Telegram AI assistant messages.
         */
        'debug' => filter_var(env('SUPPORT_AI_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),

        /*
         * AI-SUPPORT-TELEGRAM-UX-3/4/5: optional inline buttons + expand (disabled by default in UX-6).
         * AI-SUPPORT-TELEGRAM-UX-6: buttons off — copy-ready compact card only.
         */
        'telegram_actions' => [
            'enabled' => filter_var(env('SUPPORT_AI_TELEGRAM_ACTIONS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
            'collapse_long' => filter_var(env('SUPPORT_AI_TELEGRAM_COLLAPSE_LONG', '0'), FILTER_VALIDATE_BOOLEAN),
            'collapse_chars' => max(200, min(1200, (int) env('SUPPORT_AI_TELEGRAM_COLLAPSE_CHARS', 420))),
            'show_debug' => filter_var(env('SUPPORT_AI_TELEGRAM_SHOW_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
        ],
    ],
];
