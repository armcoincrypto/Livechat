<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Traits;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

trait BrickMathNumbersTrait
{
    /**
     * Приводит значение к BigDecimal.
     * Принимает строку/число, null → "0".
     */
    protected function toBigDecimal(string|int|float|null $value, ?int $scale = null): BigDecimal
    {
        $raw = $value;

        if ($raw === null || $raw === '') {
            $raw = '0';
        }

        // На всякий случай в string
        $raw = (string) $raw;

        // Убираем пробелы, запятые → точки
        $raw = str_replace([' ', ','], ['', '.'], trim($raw));

        $decimal = BigDecimal::of($raw);

        if ($scale !== null) {
            $decimal = $decimal->toScale($scale, RoundingMode::HALF_UP);
        }

        return $decimal;
    }

    /**
     * Безопасное сравнение двух значений:
     * -1 если $a < $b, 0 если равно, 1 если $a > $b.
     */
    protected function cmpNum(string|int|float $a, string|int|float $b, ?int $scale = null): int
    {
        $left  = $this->toBigDecimal($a, $scale);
        $right = $this->toBigDecimal($b, $scale);

        return $left->compareTo($right);
    }

    /**
     * Проверка "больше нуля" с BigDecimal.
     */
    protected function isGreaterThanZeroNum(string|int|float $value, ?int $scale = null): bool
    {
        return $this->cmpNum($value, '0', $scale) === 1;
    }

    /**
     * Проверка: значение пустое/нулевое → логика как у shouldUseProfile().
     */
    protected function isEmptyProfitValue(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || $value === 0
            || $value === '0'
            || $value === 0.0;
    }

    /**
     * Универсальный "эффективный" профит: сначала значение города, потом профиля, иначе null.
     */
    protected function resolveEffectiveProfit(mixed $cityValue, mixed $profileValue): ?string
    {
        if (!$this->isEmptyProfitValue($cityValue)) {
            return (string) $cityValue;
        }

        if (!$this->isEmptyProfitValue($profileValue)) {
            return (string) $profileValue;
        }

        return null;
    }

    /**
     * Возвращает эффективное значение для add_comm: сначала значение на уровне города,
     * если оно непустое ('' и null считаются пустыми), иначе значение из профиля.
     *
     * Важно: в отличие от прибыли, здесь "0", "0%" и подобные значения считаются осознанным выбором
     * и не приводят к переключению на профиль.
     */
    protected function resolveEffectiveAddComm(mixed $cityValue, mixed $profileValue): ?string
    {
        $cityRaw = $cityValue !== null ? trim((string) $cityValue) : '';
        if ($cityRaw !== '' && $cityRaw !== '0' && $cityRaw !== '0.0') {
            return $cityRaw;
        }

        $profileRaw = $profileValue !== null ? trim((string) $profileValue) : '';
        if ($profileRaw !== '' && $profileRaw !== '0' && $profileRaw !== '0.0') {
            return $profileRaw;
        }

        return null;
    }

    /**
     * Безопасное деление: $a / $b с указанным scale.
     * При делении на 0 можно:
     *  - вернуть '0'
     *  - или кинуть своё исключение (на твой выбор).
     */
    protected function divNum(string|int|float $a, string|int|float $b, int $scale): string
    {
        $left  = $this->toBigDecimal($a);
        $right = $this->toBigDecimal($b);

        if ($right->isZero()) {
            // Вариант 1: лог + '0'
            // Log::error(...);
            return '0';
            // Вариант 2: бросить исключение:
            // throw new \RuntimeException('Division by zero in divNum');
        }

        return $left
            ->dividedBy($right, $scale, RoundingMode::HALF_UP)
            ->__toString();
    }

    /**
     * Инверсия числа: 1 / $value с защитой от 0.
     */
    protected function invertNum(string|int|float $value, int $scale): string
    {
        return $this->divNum('1', $value, $scale);
    }

    protected function formatDecimal(string|int|float $value, int $scale): string
    {
        return $this->toBigDecimal($value)
            ->toScale($scale, RoundingMode::DOWN)
            ->__toString();
    }
}
