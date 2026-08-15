<?php

declare(strict_types=1);

namespace iEXPackages\BestChange\Support;

/**
 * ValueNormalizer
 *
 * Единое место нормализации значений BestChange:
 * - CSV <-> int[]
 * - step (шаг)
 * - position (позиция)
 *
 * Чистые функции, без Laravel и побочных эффектов.
 */
final class ValueNormalizer
{
    /**
     * Преобразовать массив ID в CSV-строку вида "1,2,3".
     *
     * @param mixed $value
     * @return string|null
     */
    public static function intArrayToCsv(mixed $value): ?string
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        $ids = [];
        foreach ($value as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return null;
        }

        $keys = array_keys($ids);
        sort($keys, SORT_NUMERIC);

        return implode(',', $keys);
    }

    /**
     * Преобразовать CSV-строку "1,2,3" в массив int[].
     *
     * @param string|null $csv
     * @return int[]
     */
    public static function csvToIntArray(?string $csv): array
    {
        $csv = trim((string) $csv);
        if ($csv === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $csv) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $out[$id] = true;
            }
        }

        if ($out === []) {
            return [];
        }

        $keys = array_keys($out);
        sort($keys, SORT_NUMERIC);

        return array_values($keys);
    }

    /**
     * Нормализовать шаг (step).
     *
     * Допустимые форматы:
     *  "+10", "-0.5", "*1.02", "/2", "3%", "-1.5%"
     * Также допускается запятая как десятичный разделитель.
     *
     * @param mixed $step
     * @return string
     */
    public static function normalizeStep(mixed $step): string
    {
        $raw = trim((string) ($step ?? ''));
        if ($raw === '') {
            return '0';
        }

        $raw = str_replace(',', '.', $raw);

        return preg_match('/^([+\-*\/])?\d+(?:\.\d+)?%?$/', $raw) === 1
            ? $raw
            : '0';
    }

    /**
     * Нормализовать позицию BestChange.
     *
     * Поддерживаемые форматы:
     *  - "5"
     *  - "3-7"
     *
     * Гарантии:
     *  - значения > 0
     *  - диапазон всегда в порядке min-max
     *
     * @param mixed $value
     * @return string
     */
    public static function normalizePosition(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '0';
        }

        // Одиночная позиция: "5"
        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw > 0 ? (string) (int) $raw : '0';
        }

        // Диапазон: "3-7"
        if (preg_match('/^\d+\s*-\s*\d+$/', $raw)) {
            [$a, $b] = array_map('intval', array_map('trim', explode('-', $raw, 2)));

            if ($a <= 0 || $b <= 0) {
                return '0';
            }

            return $a <= $b ? "{$a}-{$b}" : "{$b}-{$a}";
        }

        return '0';
    }
}
