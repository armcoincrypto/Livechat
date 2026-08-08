<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use App\Services\Calculator\CalculatorMathService;
use iEXPackages\BestChange\DTO\ComputedRate;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * RateCalculator
 *
 * Рассчитывает итоговый курс для BestChangeDirection на основе выбранной строки BestChange rates.
 *
 * Входные данные:
 * - BestChangeDirection (настройки направления: step, formula_value и т.п.)
 * - выбранная строка rates (changer, rate/rankrate и т.д.)
 * - весь отсортированный список rows (нужен для формул и некоторых тегов)
 * - политика выбора (какое поле использовать: rate|rankrate)
 * - внешние курсы (AggregatedRatesService) для формул
 * - карта обменников [changerId => name] для source_name
 *
 * Выход:
 * - ComputedRate:
 *   - rateValue             — итоговый курс
 *   - rateValueWithoutStep  — курс до применения шага (если применимо), иначе "0"
 *   - sourceName            — имя обменника или "Formula"/"None"
 */
final class RateCalculator
{
    use InteractsWithNumbers;

    /**
     * Точность для расчётов BestChange.
     */
    private const SCALE = 18;

    /**
     * Рассчитать курс направления по выбранной строке rates.
     *
     * Алгоритм:
     * 1) Если задана формула (formula_value):
     *    - считаем через BestChangeFormulaService
     *    - проверяем результат > 0
     *    - sourceName берём из exchanger map, иначе "Formula"
     *    - rateValueWithoutStep = "0" (так как "шага" тут может не быть или быть частью формулы)
     *
     * 2) Иначе (обычный режим):
     *    - берём поле rate|rankrate из selectedRow
     *    - преобразуем в строку bcmath масштаба SCALE
     *    - инвертируем (1 / value)
     *    - применяем step (если валиден)
     *    - сохраняем:
     *        rateValueWithoutStep = значение до шага
     *        rateValue = значение после шага
     *    - sourceName берём из exchanger map, иначе "None"
     *
     * @param BestChangeDirection $direction
     * @param array<string,mixed> $selectedRow Выбранная строка из rates (changer, rate, rankrate, reserve...)
     * @param array<int, array<string,mixed>> $sortedRows Отсортированный список rates по паре
     * @param RateSelectionPolicy $policy Политика (какое поле использовать: rate|rankrate)
     * @param array<string,string> $externalRates Внешние курсы из AggregatedRatesService::fetch()
     * @param array<int,string> $exchangersMap Карта обменников [changerId => name]
     *
     * @return ComputedRate|null null если невозможно корректно посчитать курс
     */
    public function calculate(
        BestChangeDirection $direction,
        array $selectedRow,
        array $sortedRows,
        RateSelectionPolicy $policy,
        array $externalRates,
        array $exchangersMap
    ): ?ComputedRate {
        $formula = trim((string)($direction->formula_value ?? ''));
        if ($formula !== '') {
            return $this->calculateByFormula(
                direction: $direction,
                selectedRow: $selectedRow,
                sortedRows: $sortedRows,
                policy: $policy,
                externalRates: $externalRates,
                exchangersMap: $exchangersMap,
            );
        }

        return $this->calculateByValue(
            direction: $direction,
            selectedRow: $selectedRow,
            policy: $policy,
            exchangersMap: $exchangersMap,
        );
    }

    /**
     * Расчёт по формуле.
     */
    private function calculateByFormula(
        BestChangeDirection $direction,
        array $selectedRow,
        array $sortedRows,
        RateSelectionPolicy $policy,
        array $externalRates,
        array $exchangersMap
    ): ?ComputedRate {
        $service = new BestChangeFormulaService($policy->typeField);

        $rawResult = $service->evaluate(
            formula: trim((string)$direction->formula_value),
            filtered: $sortedRows,
            externalRates: $externalRates,
            item: $direction
        );

        $result = $this->toBcString($rawResult, self::SCALE);
        if ($this->compareValues($result, '0', self::SCALE) <= 0) {
            return null;
        }

        $changerId = $this->readChangerId($selectedRow);
        $sourceName = $exchangersMap[$changerId] ?? 'Formula';

        return new ComputedRate(
            rateValue: $result,
            rateValueWithoutStep: '0',
            sourceName: $sourceName,
        );
    }

    /**
     * Расчёт без формулы: 1/(rate|rankrate) + step.
     */
    private function calculateByValue(
        BestChangeDirection $direction,
        array $selectedRow,
        RateSelectionPolicy $policy,
        array $exchangersMap
    ): ?ComputedRate {
        $field = $policy->typeField;

        $rawFieldValue = $selectedRow[$field] ?? null;
        if (!is_scalar($rawFieldValue)) {
            return null;
        }

        $fieldValue = $this->toBcString($rawFieldValue, self::SCALE);
        if ($this->compareValues($fieldValue, '0', self::SCALE) <= 0) {
            return null;
        }

        // 1 / value
        $valueWithoutStep = $this->toBcString(bcdiv('1', $fieldValue, self::SCALE), self::SCALE);

        // Применяем step (если валиден)
        $calculator = new CalculatorMathService($valueWithoutStep);
        $valueFinal = $this->applyStepIfValid($calculator, (string)($direction->step ?? ''));

        $valueFinal = $this->toBcString($valueFinal, self::SCALE);
        if ($this->compareValues($valueFinal, '0', self::SCALE) <= 0) {
            return null;
        }

        $changerId = $this->readChangerId($selectedRow);
        $sourceName = $exchangersMap[$changerId] ?? 'None';

        return new ComputedRate(
            rateValue: $valueFinal,
            rateValueWithoutStep: $valueWithoutStep,
            sourceName: $sourceName,
        );
    }

    /**
     * Применить step к калькулятору, если он валиден.
     *
     * @return string Текущее значение суммы после шага (или исходное, если step не применён)
     */
    private function applyStepIfValid(CalculatorMathService $calculator, string $stepRaw): string
    {
        $stepRaw = trim($stepRaw);
        if ($stepRaw === '' || $stepRaw === '0') {
            return $calculator->getSumma();
        }

        // мягкая нормализация и валидация выражения
        $step = $this->sanitizeFlexibleMathExpression($stepRaw, true);
        if (!$this->isValidFlexibleMathExpression($step, true)) {
            return $calculator->getSumma();
        }

        $calculator->applyStep($step);

        return $calculator->getSumma();
    }

    /**
     * Безопасно прочитать changer id из строки rates.
     */
    private function readChangerId(array $row): int
    {
        $v = $row['changer'] ?? 0;
        return is_numeric($v) ? (int)$v : 0;
    }
}
