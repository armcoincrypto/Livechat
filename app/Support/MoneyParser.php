<?php
declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * MoneyParser
 *
 * Универсальный парсер денежных значений.
 *
 * Назначение:
 * - безопасно приводить пользовательский ввод (string) к BigDecimal
 * - НЕ использовать float
 * - одинаково работать во всех правилах (amount, reserve, aml, currency_account и т.д.)
 *
 * Поведение:
 * - убирает пробелы и неразрывные пробелы
 * - заменяет запятую на точку
 * - убирает точку в конце ("10.")
 * - валидирует формат числа
 * - НЕ бросает исключения наружу
 */
final class MoneyParser
{
    /**
     * Преобразовать строку в BigDecimal.
     *
     * @param string|null $raw Входное значение (из формы / БД / API)
     * @param int $scale Количество знаков после запятой (по умолчанию 18)
     *
     * @return BigDecimal|null
     *  - BigDecimal — если строка корректна
     *  - null — если пусто или формат невалидный
     */
    public static function parse(?string $raw, int $scale = 18): ?BigDecimal
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        // Убираем обычные и неразрывные пробелы
        $value = str_replace(["\u{00A0}", ' '], '', $value);

        // Запятая → точка
        $value = str_replace(',', '.', $value);

        // Убираем точку в конце ("10.")
        $value = preg_replace('/\.$/', '', $value) ?? $value;

        // Проверяем формат числа
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return null;
        }

        try {
            return BigDecimal::of($value)->toScale($scale, RoundingMode::DOWN);
        } catch (\Throwable) {
            // Никогда не роняем приложение из-за пользовательского ввода
            return null;
        }
    }

    /**
     * Преобразовать строку в BigDecimal >= 0.
     *
     * Используется там, где отрицательные значения недопустимы
     * (лимиты, суммы, extra_out, резервы).
     *
     * @param string|null $raw
     * @param int $scale
     */
    public static function positiveOrZero(?string $raw, int $scale = 18): BigDecimal
    {
        $value = self::parse($raw, $scale);

        if ($value === null || $value->isLessThan('0')) {
            return BigDecimal::zero();
        }

        return $value;
    }
}
