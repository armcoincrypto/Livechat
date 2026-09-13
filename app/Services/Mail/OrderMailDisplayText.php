<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Blade {{ }} / htmlspecialchars require a string. Translatable CMS fields
 * (Spatie empty locale map, locale maps) must be reduced at this boundary.
 */
final class OrderMailDisplayText
{
    /**
     * @param  array<int, string>  $localePreference
     */
    public static function scalar(mixed $value, string $fallback = '', array $localePreference = []): string
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (! is_array($value)) {
            return $fallback;
        }

        if ($value === []) {
            return $fallback;
        }

        $locales = $localePreference !== []
            ? $localePreference
            : self::defaultLocalePreference();

        foreach ($locales as $locale) {
            if (! array_key_exists($locale, $value)) {
                continue;
            }
            $hit = $value[$locale];
            if (is_string($hit) && $hit !== '') {
                return $hit;
            }
        }

        $parts = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $parts[] = $item;
            }
        }

        if ($parts !== []) {
            return implode("\n", $parts);
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    public static function defaultLocalePreference(): array
    {
        $current = function_exists('app') ? (string) app()->getLocale() : '';

        $order = [];
        if ($current !== '') {
            $order[] = $current;
        }
        foreach (['en', 'ru', 'zh', 'uk', 'ka'] as $locale) {
            if (! in_array($locale, $order, true)) {
                $order[] = $locale;
            }
        }

        return $order;
    }
}
