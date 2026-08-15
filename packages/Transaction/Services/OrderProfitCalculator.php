<?php

declare(strict_types=1);

namespace iEXPackages\Transaction\Services;

use App\Models\Task;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use iEXPackages\Transaction\DTO\OrderProfitResultDto;

/**
 * Калькулятор прибыли по заявке.
 *
 * Источники прибыли:
 * - Направление: profit (%) и profit_s (фикс) → fallback на profitProfile
 * - Город: profit (%) и profit_s (фикс) → fallback на profile
 *
 * Правило fallback:
 * - null / "" / любой числовой ноль ("0", "0.0", "-0") считаются «не задано»
 * - берём следующее значение по приоритету
 *
 * Возвращает null, если:
 * - нет обязательных связей,
 * - сумма входа некорректна/<= 0,
 * - итоговая прибыль <= 0,
 * - все компоненты прибыли равны 0.
 */
final class OrderProfitCalculator
{
    private const SCALE_AMOUNT  = 8;
    private const SCALE_PERCENT = 8;
    private const ROUNDING_MODE = RoundingMode::HALF_UP;

    /**
     * Рассчитать прибыль по заявке.
     *
     * @param Task   $task     Заявка/задача обмена.
     * @param string $amountIn Сумма «отдаю» (give_price) в валюте currency1 (десятичная строка).
     */
    public function calculateForTask(Task $task, string $amountIn): ?OrderProfitResultDto
    {
        $direction = $task->direction_exchange;

        if (!$direction || !$direction->currency1 || !$direction->currency1->code_currency) {
            return null;
        }

        try {
            $amountInDec = BigDecimal::of($amountIn);
        } catch (MathException|\Throwable) {
            return null;
        }

        // Защита от деления на ноль и бессмысленных расчётов.
        if ($amountInDec->isLessThanOrEqualTo(0)) {
            return null;
        }

        $currencyCode      = (string) $direction->currency1->code_currency->name;
        $baseCurrencyCode  = 'USD';

        // Прибыль по направлению: индивидуальные значения → профиль → 0
        $directionPercentRaw = $this->resolveEffectiveProfit(
            $direction->profit,
            $direction->profitProfile?->profit
        );

        $directionFixedRaw = $this->resolveEffectiveProfit(
            $direction->profit_s,
            $direction->profitProfile?->profit_s
        );

        // Прибыль по городу: индивидуальные значения → профиль → 0
        $cityPercentRaw = '0';
        $cityFixedRaw   = '0';

        $directionCity = $task->task_info?->directionCity;

        if ($directionCity) {
            $profile = $directionCity->profile ?? null;

            $cityPercentRaw = $this->resolveEffectiveProfit(
                $directionCity->profit,
                $profile?->profit
            );

            $cityFixedRaw = $this->resolveEffectiveProfit(
                $directionCity->profit_s,
                $profile?->profit_s
            );
        }

        // Нормализация в BigDecimal (единый источник правды по нулям/сравнениям)
        try {
            $dirPercentDec  = BigDecimal::of($directionPercentRaw);
            $dirFixedDec    = BigDecimal::of($directionFixedRaw);
            $cityPercentDec = BigDecimal::of($cityPercentRaw);
            $cityFixedDec   = BigDecimal::of($cityFixedRaw);
        } catch (MathException|\Throwable) {
            return null;
        }

        if (
            $dirPercentDec->isZero()
            && $dirFixedDec->isZero()
            && $cityPercentDec->isZero()
            && $cityFixedDec->isZero()
        ) {
            return null;
        }

        // Компоненты прибыли в валюте заявки
        $dirPercentPart = $amountInDec
            ->multipliedBy($dirPercentDec)
            ->dividedBy('100', self::SCALE_AMOUNT, self::ROUNDING_MODE);

        $dirFixedPart = $dirFixedDec;

        $cityPercentPart = $amountInDec
            ->multipliedBy($cityPercentDec)
            ->dividedBy('100', self::SCALE_AMOUNT, self::ROUNDING_MODE);

        $cityFixedPart = $cityFixedDec;

        $profitAmountDec = $dirPercentPart
            ->plus($dirFixedPart)
            ->plus($cityPercentPart)
            ->plus($cityFixedPart);

        if ($profitAmountDec->isLessThanOrEqualTo(0)) {
            return null;
        }

        // Эффективный % прибыли от суммы «отдаю»
        $effectivePercentDec = $profitAmountDec
            ->multipliedBy('100')
            ->dividedBy($amountInDec, self::SCALE_PERCENT, self::ROUNDING_MODE);

        // Конвертация прибыли в USD
        $rateToUsd = $this->getRateToUsd($currencyCode, $baseCurrencyCode);

        try {
            $rateDec = BigDecimal::of($rateToUsd);
        } catch (MathException|\Throwable) {
            $rateDec = BigDecimal::zero();
        }

        $profitUsdDec = $profitAmountDec->multipliedBy($rateDec);

        // Детализация (по умолчанию показываем только положительные компоненты)
        $components = [];

        if ($dirPercentDec->isGreaterThan(0)) {
            $components[] = [
                'key'      => 'direction_percent',
                'title'    => 'Процент прибыли по направлению',
                'amount'   => (string) $dirPercentPart,
                'currency' => $currencyCode,
                'percent'  => (string) $dirPercentDec,
            ];
        }

        if ($dirFixedDec->isGreaterThan(0)) {
            $components[] = [
                'key'      => 'direction_fixed',
                'title'    => 'Фиксированная прибыль по направлению',
                'amount'   => (string) $dirFixedPart,
                'currency' => $currencyCode,
            ];
        }

        if ($cityPercentDec->isGreaterThan(0)) {
            $components[] = [
                'key'      => 'city_percent',
                'title'    => 'Процент прибыли по городу',
                'amount'   => (string) $cityPercentPart,
                'currency' => $currencyCode,
                'percent'  => (string) $cityPercentDec,
            ];
        }

        if ($cityFixedDec->isGreaterThan(0)) {
            $components[] = [
                'key'      => 'city_fixed',
                'title'    => 'Фиксированная прибыль по городу',
                'amount'   => (string) $cityFixedPart,
                'currency' => $currencyCode,
            ];
        }

        $ratesSnapshot = [
            "{$currencyCode}_{$baseCurrencyCode}" => $rateToUsd,
        ];

        return new OrderProfitResultDto(
            profitAmount:       (string) $profitAmountDec,
            profitCurrencyCode: $currencyCode,
            profitAmountUsd:    (string) $profitUsdDec,
            baseCurrencyCode:   $baseCurrencyCode,
            effectivePercent:   (string) $effectivePercentDec,
            components:         $components,
            ratesSnapshot:      $ratesSnapshot,
        );
    }

    /**
     * Возвращает итоговое значение по приоритету:
     *  1) значение сущности (направление/город)
     *  2) значение профиля
     *  3) "0"
     *
     * Ноль в любой форме считается «не задано» и включает fallback.
     */
    private function resolveEffectiveProfit(mixed $entityValue, mixed $profileValue): string
    {
        $v = $this->normalizeOptionalNumber($entityValue);
        if ($v !== null) {
            return $v;
        }

        $v = $this->normalizeOptionalNumber($profileValue);
        if ($v !== null) {
            return $v;
        }

        return '0';
    }

    /**
     * Нормализует значение к десятичной строке или возвращает null, если значение «не задано».
     *
     * «Не задано»:
     * - null
     * - пустая строка
     * - любой числовой ноль (0, "0", "0.0", "-0", "000")
     */
    private function normalizeOptionalNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            try {
                $dec = BigDecimal::of((string) $value);
                return $dec->isZero() ? null : (string) $dec;
            } catch (MathException|\Throwable) {
                return null;
            }
        }

        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    /**
     * Курс 1 единицы валюты к базовой валюте (по умолчанию USD).
     * Использует helper calculator_converter($from, $base, '1').
     */
    private function getRateToUsd(string $fromCurrency, string $baseCurrency = 'USD'): string
    {
        $from = mb_strtoupper($fromCurrency);
        $base = mb_strtoupper($baseCurrency);

        if ($from === $base) {
            return '1';
        }

        return (string) calculator_converter($from, $base, 1);
    }
}
