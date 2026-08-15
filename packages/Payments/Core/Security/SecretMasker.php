<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Security;

final class SecretMasker
{
    private function __construct() {}

    public static function placeholder(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return '** Параметр заполнен **';
    }

    /**
     * Для логов/отладок: оставляем только первые/последние символы.
     */
    public static function mask(string $value, int $keepStart = 2, int $keepEnd = 2): string
    {
        $value = (string) $value;
        $len = mb_strlen($value, 'UTF-8');

        if ($len <= ($keepStart + $keepEnd)) {
            return str_repeat('•', max(4, $len));
        }

        $start = mb_substr($value, 0, $keepStart, 'UTF-8');
        $end   = mb_substr($value, $len - $keepEnd, $keepEnd, 'UTF-8');

        return $start . str_repeat('•', 8) . $end;
    }
}
