<?php

declare(strict_types=1);

namespace iEXPackages\SmartMailer\Support;

/**
 * Blade-safe stringification for order field payloads.
 *
 * Some task fields store JSON/array values; Laravel's {{ }} encoding calls
 * htmlspecialchars() which TypeErrors on arrays (failed OrderCompletedMailJob).
 */
final class MailFieldValue
{
    public static function display(mixed $value, string $fallback = ''): string
    {
        if ($value === null) {
            return $fallback;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $parts[] = (string) $item;
                } else {
                    $encoded = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $parts[] = $encoded === false ? '' : $encoded;
                }
            }

            return implode(', ', $parts);
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? $fallback : $encoded;
    }
}
