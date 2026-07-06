<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

/**
 * Redact PII and sensitive identifiers from support text (playbook export only).
 * Draft context uses separate preservation rules in SupportAiDraftService.
 */
final class SupportAiAnonymizer
{
    public static function anonymizeForPlaybook(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[email]', $text) ?? $text;
        $text = preg_replace('/\b(?:\+?\d[\d\s().-]{7,}\d)\b/u', '[phone]', $text) ?? $text;
        $text = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $text) ?? $text;
        $text = preg_replace('/\b\d{10,}\b/u', '[order_id]', $text) ?? $text;
        $text = preg_replace('/\bS-\d{4,12}\b/i', '[support_id]', $text) ?? $text;
        $text = preg_replace('#/order/\d{4,20}#i', '/order/[order_id]', $text) ?? $text;
        $text = preg_replace('/\b0x[0-9a-fA-F]{40,64}\b/', '[tx_hash]', $text) ?? $text;
        $text = preg_replace('/\b[0-9a-fA-F]{64}\b/', '[tx_hash]', $text) ?? $text;
        $text = preg_replace(
            '/\b\d+(?:[.,]\d+)?\s*(?:USDT|USD|BTC|ETH|RUB|EUR|GEL|UAH|TRX|TON|LTC|XRP)\b/iu',
            '[amount]',
            $text
        ) ?? $text;
        $text = preg_replace(
            '/\b(?:[А-ЯЁA-Z][а-яёa-z]+(?:\s+[А-ЯЁA-Z][а-яёa-z]+){1,3})\b/u',
            '[name]',
            $text
        ) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
