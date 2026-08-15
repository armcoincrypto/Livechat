<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use App\Services\Calculator\CalculatorMathService;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * CompetitorStrategy
 *
 * Источник курса: competitor_rates.
 *
 * Правила:
 * - Берём competitor_rates.summa.
 * - Проверяем лимиты (cr_min_sum/cr_max_sum).
 * - Если курс вне лимитов → берём альтернативный курс cr_new_rate.summa и применяем надбавку cr_add_course (%).
 * - Надбавка по бизнес-логике только положительная.
 *
 * Производительность:
 * - CalculatorMathService создаётся только если курс вышел за лимиты.
 *
 * Безопасность:
 * - Null-safe доступ к relations (?->), чтобы не ловить ошибки.
 */
final class CompetitorStrategy implements StrategyInterface
{
    use InteractsWithNumbers;

    public function __construct(
        private readonly DirectionExchange $directionExchange
    ) {}

    public function getRate(): string
    {
        $courseValue = $this->sanitizeNumber(
            (string) ($this->directionExchange->competitor_rates?->summa ?? '0')
        );

        if (!$this->isGreaterThanZero($courseValue)) {
            return '0';
        }

        $min = $this->sanitizeNumber((string) ($this->directionExchange->cr_min_sum ?? '0'));
        $max = $this->sanitizeNumber((string) ($this->directionExchange->cr_max_sum ?? '0'));

        // В лимитах — возвращаем исходный курс без лишних вычислений
        if ($this->isWithinCourseLimits($courseValue, $min, $max)) {
            return $courseValue;
        }

        // Вне лимитов — берём альтернативный курс, если он есть
        $newRate = $this->sanitizeNumber(
            (string) ($this->directionExchange->cr_new_rate?->summa ?? $courseValue)
        );

        $math = new CalculatorMathService($newRate);

        // Надбавка (по бизнес-логике только положительная)
        $addCourse = $this->sanitizeNumber((string) ($this->directionExchange->cr_add_course ?? '0'));
        if ($this->isGreaterThanZero($addCourse)) {
            $math->calculateWithPercentage($addCourse);
        }

        return $math->getSumma();
    }
}
