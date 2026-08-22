<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

/**
 * Outbound + webhook bot identity.
 *
 * TELEGRAM_OPERATOR_BOT_TOKEN wins so the canonical operational bot
 * (@exswapingnotyfbot) can be selected without rewriting telegram_notifications
 * (which may still hold a secondary notifier token).
 */
final class TelegramBotTokenResolver
{
    public function resolve(?string $notificationToken = null): string
    {
        $fromConfig = trim((string) config('telegram_operator.bot_token', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return trim((string) $notificationToken);
    }
}
