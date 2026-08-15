<?php

declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use App\Services\Calculator\CalculatorMathService;
use iEXPackages\Calculator\Services\CourseLimitService;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\BrickMathNumbersTrait;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * Класс для расчета курса через BestChange с учетом ограничений и корректировок.
 */
class BestChangeStrategy implements StrategyInterface
{
    use InteractsWithNumbers, BrickMathNumbersTrait;

    protected ?BestChangeDirection $bestChangeDirection = null;
    protected CalculatorMathService $muchValue;

    /**
     * Конструктор инициализирует направление обмена и связанный курс.
     *
     * @param DirectionExchange $directionExchange Направление обмена
     */
    public function __construct(
        protected DirectionExchange $directionExchange
    ) {
        $this->bestChangeDirection = $this->directionExchange->bestchange_directions instanceof BestChangeDirection
            ? $this->directionExchange->bestchange_directions
            : null;
    }

    /**
     * Получает и корректирует текущий курс, включая проверки границ и шага.
     *
     * @return string Итоговый курс после всех применений
     */
    public function getRate(): string
    {
        if (!$this->bestChangeDirection) {
            return '0';
        }

        $rate = $this->sanitizeNumber($this->bestChangeDirection->rate_value ?? '0');
        if (!$this->isGreaterThanZero($rate)) {
            return '0';
        }

        $this->muchValue = new CalculatorMathService($rate);


        // Нормализуем min/max для проверки
        $min = $this->sanitizeNumber($this->bestChangeDirection->min_sum ?? '0');
        $max = $this->sanitizeNumber($this->bestChangeDirection->max_sum ?? '0');

        $limit = new CourseLimitService();

        // true => вышли за пределы => применяем альтернативы
        if ($limit->check($rate, $min, $max)) {
            $this->applyOutOfRangeProcessing();
        }

        return $this->muchValue->getSumma();
    }

    /**
     * Применяет альтернативные курсы, если основной курс выходит за допустимые границы.
     *
     * Проверяет парсеры и стандартный курс в порядке приоритета.
     */
    private function applyOutOfRangeProcessing(): void
    {
        $bc = $this->bestChangeDirection;
        if (!$bc) {
            return;
        }

        // reset_course должен быть строго 1
        if ((int)($bc->reset_course ?? 0) !== 1) {
            return;
        }

        $scale = (int) iEXSetting('max_decimal_places', 18);

        // 1) default parser (если включён и есть комиссия)
        if (
            $this->cmpNum($this->sanitizeNumber($bc->min_sum_new_default_parser ?? '0'), '0', $scale) === 1
            && isset($bc->new_default_parser)
        ) {
            $feeRaw = (string)($bc->min_sum_new_default_parser_fee ?? '');
            if ($this->isValidFlexibleMathExpression($feeRaw)) {
                $amount = $this->sanitizeNumber($bc->new_default_parser->summa ?? '0');
                $this->muchValue->setCurrentValue($amount);

                $fee = $this->sanitizeFlexibleMathExpression($feeRaw);
                if ($this->cmpNum($fee, '0', $scale) !== 0) {
                    $this->muchValue->calculate($fee);
                }
                return;
            }
        }

        // 2) formula parser
        if (
            $this->cmpNum($this->sanitizeNumber($bc->min_sum_new_formula_parser ?? '0'), '0', $scale) === 1
            && isset($bc->new_formula_parser)
        ) {
            $feeRaw = (string)($bc->min_sum_new_formula_parser_fee ?? '');
            if ($this->isValidFlexibleMathExpression($feeRaw)) {
                $amount = $this->sanitizeNumber($bc->new_formula_parser->summa ?? '0');
                $this->muchValue->setCurrentValue($amount);

                $fee = $this->sanitizeFlexibleMathExpression($feeRaw);
                if ($this->cmpNum($fee, '0', $scale) !== 0) {
                    $this->muchValue->calculate($fee);
                }
                return;
            }
        }

        // 3) fallback: стандартный курс
        $standard = $this->sanitizeNumber($bc->standard_course ?? '0');
        $this->muchValue->setCurrentValue($standard);
    }
}
