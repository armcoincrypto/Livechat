<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Drivers\Didit;

/**
 * Privacy-safe operator copy helpers for Didit approval notices.
 * No PII beyond masked email and shortened session reference.
 */
final class DiditOperatorNoticePrivacy
{
    public static function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        $at = strpos($email, '@');
        if ($at === false || $email === '') {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $keep = min(3, max(0, strlen($local)));

        return substr($local, 0, $keep).'***@'.$domain;
    }

    public static function maskSessionId(?string $sessionId): string
    {
        $sessionId = trim((string) $sessionId);
        if ($sessionId === '') {
            return '********';
        }
        if (strlen($sessionId) <= 8) {
            return '********';
        }

        return substr($sessionId, 0, 4).'…'.substr($sessionId, -4);
    }
}
