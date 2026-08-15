<?php

declare(strict_types=1);

namespace iEXPackages\Calculator\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;

final class CourseLimitService
{
    /**
     * Проверяет, выходит ли сумма за границы min/max.
     *
     * @param string|float|null $amount Проверяемая сумма
     * @param string|float|null $min Минимальная сумма (0 — нет ограничения)
     * @param string|float|null $max Максимальная сумма (0 — нет ограничения)
     *
     * @return bool true — сумма выходит за границы, false — в пределах
     */
    public function check(string|float|null $amount, string|float|null $min, string|float|null $max): bool
    {
        $scale = (int) iEXSetting('max_decimal_places', 18);

        $amountN = $this->normalizeNumber($amount, $scale);
        $minN    = $this->normalizeNumber($min, $scale);
        $maxN    = $this->normalizeNumber($max, $scale);

        // Если оба лимита = 0 — ограничений нет
        if ($this->isZero($minN) && $this->isZero($maxN)) {
            return false;
        }

        // Логируем только реально подозрительные случаи
        if ($this->looksInvalidButNotEmpty($amount, $amountN)) {
            Log::warning('CourseLimitService: amount looks invalid, normalized to 0', [
                'amount_raw' => $amount,
                'amount_norm' => $amountN,
                'min_norm' => $minN,
                'max_norm' => $maxN,
            ]);
        }

        // amount < min (если min > 0)
        if (!$this->isZero($minN) && $this->cmp($amountN, $minN) === -1) {
            return true;
        }

        // amount > max (если max > 0)
        if (!$this->isZero($maxN) && $this->cmp($amountN, $maxN) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Нормализует число в десятичную строку без потери точности.
     */
    private function normalizeNumber(string|float|null $value, int $scale): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '' ||
            strcasecmp($raw, 'nan') === 0 ||
            strcasecmp($raw, 'inf') === 0 ||
            strcasecmp($raw, 'infinity') === 0 ||
            $raw === '∞'
        ) {
            return '0';
        }

        try {
            // запятые → точки, убираем все пробелы и NBSP
            $normalized = str_replace(',', '.', $raw);
            $normalized = preg_replace('/[\x{00A0}\s]+/u', '', $normalized);

            // допускаем ведущий "+"
            if ($normalized !== '' && $normalized[0] === '+') {
                $normalized = substr($normalized, 1);
            }

            $decimal = BigDecimal::of($normalized)->toScale($scale, RoundingMode::DOWN);
            $out = $decimal->__toString();

            // убираем хвостовые нули
            if (str_contains($out, '.')) {
                $out = rtrim($out, '0');
                $out = rtrim($out, '.');
            }

            // нормализуем -0 → 0
            if ($out === '-0' || preg_match('/^-?0(?:\.0+)?$/', $out)) {
                return '0';
            }

            return $out;
        } catch (\Throwable) {
            return '0';
        }
    }

    /**
     * Сравнение двух чисел в строковом виде.
     */
    private function cmp(string $a, string $b): int
    {
        return BigDecimal::of($a)->compareTo(BigDecimal::of($b));
    }

    /**
     * Проверка "строго ноль".
     */
    private function isZero(string $v): bool
    {
        return $v === '0' || preg_match('/^0(?:\.0+)?$/', $v) === 1;
    }

    /**
     * Определяет подозрительные случаи:
     * исходное значение не пустое, но после нормализации стало "0".
     */
    private function looksInvalidButNotEmpty(string|float|null $raw, string $norm): bool
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return false;
        }

        if ($norm !== '0') {
            return false;
        }

        // допустимые форматы:
        // 123 | -1.23 | 1e-5 | -2.3E+7 | 1,25
        return !preg_match(
            '/^[+\-]?\d+(?:[.,]\d+)?(?:[eE][+\-]?\d+)?$/u',
            $s
        );
    }
}
