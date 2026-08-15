<?php

declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Support;

/**
 * Safe display masking for verification_card account/card identifiers.
 * Does not mutate stored values. Prefer this over opaque encoded helpers.
 */
final class MaskVerificationIdentifier
{
    public static function mask(?string $value): string
    {
        $raw = preg_replace('/\s+/u', '', (string) $value);
        if ($raw === null || $raw === '') {
            return '';
        }

        // Prefer legacy helper when present and returning a reduced form.
        if (function_exists('getEncryptedCardNumber')) {
            try {
                $legacy = (string) getEncryptedCardNumber($raw);
                if ($legacy !== '' && $legacy !== $raw && mb_strlen($legacy) <= mb_strlen($raw)) {
                    return $legacy;
                }
            } catch (\Throwable) {
                // fall through to local mask
            }
        }

        $len = mb_strlen($raw);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        if ($len <= 8) {
            return str_repeat('*', $len - 2) . mb_substr($raw, -2);
        }

        $last4 = mb_substr($raw, -4);
        // Card-like grouping when digits dominate.
        if (preg_match('/^\d{12,19}$/', $raw) === 1) {
            return '**** **** **** ' . $last4;
        }

        $prefix = mb_substr($raw, 0, 2);
        return $prefix . str_repeat('*', max(4, $len - 6)) . $last4;
    }
}
