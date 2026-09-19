<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

/**
 * Strip Telegram bot tokens from exception/log text.
 * Guzzle includes the request URI (`/bot<token>/sendMessage`) in ClientException messages.
 */
final class TelegramSecretRedactor
{
    public static function message(string $text): string
    {
        $redacted = preg_replace(
            '#https?://api\.telegram\.org/bot[^/\s]+#i',
            'https://api.telegram.org/bot[redacted]',
            $text
        );
        $text = is_string($redacted) ? $redacted : $text;

        $redacted = preg_replace(
            '#\bbot\d+:[A-Za-z0-9_-]{20,}#',
            'bot[redacted]',
            $text
        );

        return is_string($redacted) ? $redacted : $text;
    }
}
