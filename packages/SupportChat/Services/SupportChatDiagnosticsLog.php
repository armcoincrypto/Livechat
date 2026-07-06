<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use Illuminate\Support\Facades\Log;

/**
 * P4.2 structured support-chat observability (admin/internal; never log secrets).
 */
final class SupportChatDiagnosticsLog
{
    public const EVENT_MESSAGE_SENT = 'support_chat_message_sent';

    public const EVENT_MESSAGE_FAILED = 'support_chat_message_failed';

    public const EVENT_ATTACHMENT_UPLOADED = 'support_chat_attachment_uploaded';

    public const EVENT_ATTACHMENT_FAILED = 'support_chat_attachment_failed';

    public const EVENT_TELEGRAM_SEND_FAILED = 'support_chat_telegram_send_failed';

    public const EVENT_TOPIC_CREATE_FAILED = 'support_chat_topic_create_failed';

    public const EVENT_WEBHOOK_RECEIVED = 'support_chat_webhook_received';

    public const EVENT_WEBHOOK_FAILED = 'support_chat_webhook_failed';

    public const EVENT_OPERATOR_REPLY = 'support_chat_operator_reply';

    public const EVENT_SPAM_REJECTED = 'support_chat_spam_rejected';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function messageSent(array $context = []): void
    {
        self::write(self::EVENT_MESSAGE_SENT, 'info', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function messageFailed(array $context = []): void
    {
        self::write(self::EVENT_MESSAGE_FAILED, 'warning', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function attachmentUploaded(array $context = []): void
    {
        self::write(self::EVENT_ATTACHMENT_UPLOADED, 'info', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function attachmentFailed(array $context = []): void
    {
        self::write(self::EVENT_ATTACHMENT_FAILED, 'warning', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function telegramSendFailed(array $context = []): void
    {
        self::write(self::EVENT_TELEGRAM_SEND_FAILED, 'warning', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function topicCreateFailed(array $context = []): void
    {
        self::write(self::EVENT_TOPIC_CREATE_FAILED, 'warning', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function webhookReceived(array $context = []): void
    {
        self::write(self::EVENT_WEBHOOK_RECEIVED, 'info', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function webhookFailed(array $context = []): void
    {
        self::write(self::EVENT_WEBHOOK_FAILED, 'error', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function operatorReply(array $context = []): void
    {
        self::write(self::EVENT_OPERATOR_REPLY, 'info', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function spamRejected(array $context = []): void
    {
        self::write(self::EVENT_SPAM_REJECTED, 'warning', $context);
        SupportChatMetrics::incrementSpamReject();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function adminRetry(string $action, array $context = []): void
    {
        self::write('support_chat_admin_retry', 'info', array_merge(['action' => $action], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $event, string $level, array $context): void
    {
        if (! filter_var(config('support_chat.diagnostics.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $payload = self::sanitize($context);
        if (! isset($payload['event'])) {
            $payload['event'] = $event;
        }

        Log::log($level, $event, $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function sanitize(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $k = strtolower((string) $key);
            if (str_contains($k, 'token') || str_contains($k, 'secret') || str_contains($k, 'password')) {
                continue;
            }
            if ($k === 'path' || $k === 'disk' || $k === 'file_path' || $k === 'absolute') {
                continue;
            }
            if ($k === 'body' || $k === 'message' || $k === 'caption') {
                continue;
            }
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500).'…';
            }
            if (is_array($value)) {
                $value = self::sanitize($value);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public static function sanitizeError(?string $error): ?string
    {
        if ($error === null || $error === '') {
            return null;
        }
        $t = trim($error);
        if (preg_match('/bot\d+:[A-Za-z0-9_-]+/i', $t) === 1) {
            return 'telegram_api_error';
        }
        if (strlen($t) > 255) {
            return substr($t, 0, 252).'…';
        }

        return $t;
    }
}
