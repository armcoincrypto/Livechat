<?php

namespace iEXPackages\Courses\Export\Concerns;

use App\Models\DirectionExchange;
use Illuminate\Database\Eloquent\Builder;

trait ManagesAttributes
{
    /**
     * Конфигурация направлений для экспорта курсов
     */
    protected function builder(): Builder
    {
        return DirectionExchange::with([
            // Валюта "Отдаю"
            'currency1:id,id_code_currency,number_format,status,designation_xml',
            'currency1.code_currency:id,name',
            'currency1.reserve:id,id_currency,summa',

            // Валюта "Получаю"
            'currency2:id,id_code_currency,number_format,status,designation_xml',
            'currency2.code_currency:id,name',
            'currency2.reserve:id,id_currency,summa',

            // Остальная логика экспорта
            'bestchange_directions',
            'direction_exchange_cities',
            'direction_exchange_cities.city',
            'groupCommissions',
            'selectorFees',
            'parser_exchange',
            'rl_parser_exchange',
            'parser_formula_rates',
            'file_parser_rates',
            'competitor_rates',
            'cr_new_rate',
            'direction_exchange_percentage_amount',
        ])
            ->whereHas('currency1', fn ($q) => $q->where('status', 0))
            ->whereHas('currency2', fn ($q) => $q->where('status', 0))
            ->where('is_error_rate', 0)
            ->where('status', 1);
    }
}
