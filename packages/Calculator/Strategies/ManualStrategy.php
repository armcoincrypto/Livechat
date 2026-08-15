<?php
    declare(strict_types=1);

    namespace iEXPackages\Calculator\Strategies;

    use App\Models\DirectionExchange;
    use iEXPackages\Calculator\Contracts\StrategyInterface;
    use iEXPackages\Calculator\Traits\InteractsWithNumbers;

    class ManualStrategy implements StrategyInterface
    {
        use InteractsWithNumbers;

        public function __construct(
            protected DirectionExchange $directionExchange
        ) {}


        /**
         * Получает ручной курс с поддержкой высокоточных чисел (crypto).
         *
         * @return string Итоговое значение курса в виде строки для сохранения точности.
         */
        public function getRate(): string
        {
            $rate = $this->sanitizeNumber($this->directionExchange->manual_rate_value ?? '0');

            return $this->isGreaterThanZero($rate) ? $rate : '0';
        }
    }
