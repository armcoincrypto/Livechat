<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

/**
 * Fail-closed internal return path sanitizer (mirrors owned FE safeReturnUrl).
 */
final class OAuthReturnToSanitizer
{
    private const LOCALES = ['en', 'ru', 'uk', 'ka', 'zh'];

    public static function sanitize(mixed $raw): ?string
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $lower = strtolower($value);
        foreach (['//', 'javascript:', 'data:', 'vbscript:', 'file:'] as $blocked) {
            if (str_starts_with($lower, $blocked)) {
                return null;
            }
        }

        if (preg_match('#^https?:#i', $value)) {
            return null;
        }
        if (str_contains($value, '\\') || str_contains($value, "\0") || str_contains($value, "\n") || str_contains($value, "\r")) {
            return null;
        }
        if (str_contains($value, '@')) {
            return null;
        }

        if (!str_starts_with($value, '/')) {
            $value = '/'.$value;
        }

        try {
            $decoded = rawurldecode($value);
            if ($decoded !== $value && preg_match('#https?:#i', $decoded)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $localeOk = false;
        foreach (self::LOCALES as $locale) {
            if ($value === '/'.$locale || str_starts_with($value, '/'.$locale.'/')) {
                $localeOk = true;
                break;
            }
        }
        if (!$localeOk) {
            return null;
        }

        if (str_contains($value, '/auth/')) {
            return null;
        }

        return $value;
    }
}
