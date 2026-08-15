<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use App\Services\Calculator\CalculatorMathService;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;
use Illuminate\Support\Facades\Log;
use Throwable;

class DefaultStrategy implements StrategyInterface
{
    use InteractsWithNumbers;

    public function __construct(
        protected DirectionExchange $directionExchange
    ) {}


    /**
     * Рассчитывает базовый курс с учетом комиссий.
     *
     * @return string Итоговый курс с учетом всех комиссий.
     */
    public function getRate(): string
    {
        try {
            $courseValue = $this->sanitizeNumber($this->directionExchange->parser_exchange->summa ?? '0');

            if (!$this->isGreaterThanZero($courseValue)) {
                return '0';
            }

            $mathValue = new CalculatorMathService($courseValue);

            $this->applyPercentageCommission($mathValue);
            $this->applyCurrencyCommission($mathValue);

            return $mathValue->getSumma();
        } catch (Throwable $e) {
            Log::error('DefaultStrategy: error', [
                'message' => $e->getMessage(),
                'direction_exchange_id' => $this->directionExchange->id ?? null,
            ]);
            return '0';
        }
    }

    /**
     * Применяет процентную комиссию, проверяя деление на ноль.
     *
     * @param CalculatorMathService $mathValue Объект калькулятора с текущим курсом.
     */
    private function applyPercentageCommission(CalculatorMathService $mathValue): void
    {
        $addPercentage = $this->sanitizeNumber($this->directionExchange->add_course1 ?? '0');

        if (!$this->isGreaterThanZero($addPercentage) || !$this->isGreaterThanZero($mathValue->getSumma())) {
            return;
        }

        $mathValue->calculateWithPercentage($addPercentage);
    }

    /**
     * Применяет валютную комиссию, проверяя деление на ноль.
     *
     * @param CalculatorMathService $mathValue Объект калькулятора с текущим курсом.
     */
    private function applyCurrencyCommission(CalculatorMathService $mathValue): void
    {
        $addCurrency = $this->sanitizeNumber($this->directionExchange->add_course1_s ?? '0');

        if (!$this->isGreaterThanZero($addCurrency) || !$this->isGreaterThanZero($mathValue->getSumma())) {
            return;
        }

        $mathValue->calculateWithValue($addCurrency);
    }
}
