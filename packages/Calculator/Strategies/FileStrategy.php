<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

class FileStrategy implements StrategyInterface
{
    use InteractsWithNumbers;

    public function __construct(
        protected DirectionExchange $directionExchange
    ) {}

    /**
     * Рассчитывает курс из файла с поддержкой высокоточных (crypto) значений.
     *
     * @return string Итоговый курс в виде строки для обеспечения точности.
     */
    public function getRate(): string
    {
        $courseValue = $this->sanitizeNumber(
            $this->directionExchange->file_parser_rates->summa ?? '0'
        );

        return $this->isGreaterThanZero($courseValue) ? $courseValue : '0';
    }
}
