<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Lightweight counters for admin health (no PII). */
final class SupportChatMetrics
{
    private const WEBHOOK_KEY = 'support_chat:metrics:last_webhook_at';

    private const SPAM_PREFIX = 'support_chat:metrics:spam_rejects:';

    public static function recordWebhookReceived(): void
    {
        Cache::put(self::WEBHOOK_KEY, now()->toIso8601String(), now()->addDays(14));
    }

    public static function lastWebhookReceivedAt(): ?string
    {
        $v = Cache::get(self::WEBHOOK_KEY);

        return is_string($v) && $v !== '' ? $v : null;
    }

    public static function incrementSpamReject(): void
    {
        $key = self::SPAM_PREFIX.now()->format('Y-m-d');
        $n = (int) Cache::get($key, 0);
        Cache::put($key, $n + 1, now()->addDays(3));
    }

    public static function spamRejectsLast24h(): int
    {
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $sum = (int) Cache::get(self::SPAM_PREFIX.$today, 0);
        if ($yesterday !== $today) {
            $sum += (int) Cache::get(self::SPAM_PREFIX.$yesterday, 0);
        }

        return $sum;
    }

    public static function incrementAttachmentUploadFailed(): void
    {
        $key = 'support_chat:metrics:attachment_upload_failed:'.now()->format('Y-m-d');
        $n = (int) Cache::get($key, 0);
        Cache::put($key, $n + 1, now()->addDays(3));
    }
}
