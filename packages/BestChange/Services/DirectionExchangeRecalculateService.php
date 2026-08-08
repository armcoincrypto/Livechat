<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\CalculatorFacade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * DirectionExchangeRecalculateService
 *
 * Пересчитывает таблицу direction_exchange после обновления bestchange_directions.
 *
 * Важно по производительности:
 * - Убираем N+1 на профилях городов: калькулятор использует $city->profile (hasOneThrough),
 *   поэтому обязательно eager-load direction_exchange_cities.profile.
 * - Загружаем обе системы “комиссий выбора” (если калькулятор использует обе):
 *   - selectorFees (belongsToMany SelectorFee)
 *   - direction_exchange_selector_fees (hasMany DirectionExchangeSelectorFee)
 */
final class DirectionExchangeRecalculateService
{
    /**
     * Выполнить пересчёт всех направлений, которые используют BestChange.
     *
     * @param callable(DirectionExchange,\Throwable):void|null $onError
     */
    public function run(?callable $onError = null): void
    {
        $baseQuery = DirectionExchange::query()
            ->where('status', 1)
            // ZELLEUSD outgoing owned exclusively by ZelleUsdUsdtBenchmarkAuthority.
            // Never recalculate from BestChange ZELLE market offers (circularity / wrong family).
            ->where(
                'id_currency1',
                '!=',
                \App\Services\Rates\ZelleOutgoingRateWriteGuard::zelleCurrencyId()
            )
            ->whereHas('bestchange_directions', static fn ($q) => $q->where('status', 1))
            ->with([

                'currency1:id,number_format,id_payment,id_code_currency',
                'currency1.payment:id,name',
                'currency1.code_currency:id,name,sign',

                'currency2:id,number_format,id_payment,id_code_currency',
                'currency2.payment:id,name',
                'currency2.code_currency:id,name,sign',

                'direction_exchange_cities.profile:direction_city_profiles.id,direction_city_profiles.name,direction_city_profiles.add_comm,direction_city_profiles.profit,direction_city_profiles.profit_s',
                'direction_exchange_cities.cityProfiles:direction_city_profiles.id,direction_city_profiles.name,direction_city_profiles.add_comm,direction_city_profiles.profit,direction_city_profiles.profit_s',

                'groupCommissions:id,receiving',

                'bestchange_directions' => static fn ($q) => $q->where('status', 1),

                'bestchange_directions.new_default_parser:id,summa,status',
                'bestchange_directions.new_formula_parser:id,summa,status',

                'direction_exchange_percentage_amount:id,id_direction_exchange,percentage,from_amount,to_amount',

                'selectorFees:id,name,description,fee', // belongsToMany SelectorFee
                'direction_exchange_selector_fees:id,id_direction_exchange,fee,name,description', // hasMany DirectionExchangeSelectorFee

                // --- Parsers / file parsers / formula parsers
                'parser_exchange:id,id_group,value,summa,status',
                'parser_exchange.group_parse_exchange:id,name',

                'file_parser_rates:id,id_group,value,summa,number_format,status',
                'file_parser_rates.file_parser_group:id,name',

                'parser_formula_rates:id,value,summa,number_format,status',
            ])
            ->orderBy('id');

        $baseQuery->chunkById(500, function ($exchanges) use ($onError): void {
            $now = Carbon::now()->toDateTimeString();

            $successUpdates = [];
            $errorUpdates = [];

            foreach ($exchanges as $exchange) {
                try {
                    $calculator = CalculatorFacade::setDirectionExchange($exchange)->withoutOptions()->calculate();
                    $courseValue = (string) $calculator->getRateValue();

                    // Never wipe a healthy manual/baseline course when BestChange
                    // is enabled but yields no positive market rate.
                    if (!is_numeric($courseValue) || (float) $courseValue <= 0) {
                        $errorUpdates[] = [
                            'id'                 => (int) $exchange->id,
                            'is_error_rate'      => 1,
                            'error_rate_text'    => 'bestchange_recalculate_non_positive_rate',
                            'parser_source_name' => 'BestChange',
                            'updated_at'         => $now,
                        ];
                        continue;
                    }

                    $successUpdates[] = [
                        'id'                 => (int) $exchange->id,
                        'course_value'       => $courseValue,
                        'exchange_rate'      => (string) $calculator->getFullRate(),
                        'is_error_rate'      => 0,
                        'error_rate_text'    => null,
                        'parser_source_name' => 'BestChange',
                        'updated_at'         => $now,
                    ];
                } catch (\Throwable $e) {
                    $errorUpdates[] = [
                        'id'                 => (int) $exchange->id,
                        'is_error_rate'      => 1,
                        'error_rate_text'    => $e->getMessage(),
                        'parser_source_name' => 'BestChange',
                        'updated_at'         => $now,
                    ];

                    if ($onError) {
                        $onError($exchange, $e);
                    } else {
                        Log::error('DirectionExchangeRecalculateService error', [
                            'direction_exchange_id' => (int) $exchange->id,
                            'tech_name' => $exchange->tech_name ?? null,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($successUpdates !== []) {
                DirectionExchange::upsert(
                    $successUpdates,
                    ['id'],
                    ['course_value', 'exchange_rate', 'is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']
                );
            }

            if ($errorUpdates !== []) {
                // Важно: при ошибке НЕ затираем course_value/exchange_rate
                DirectionExchange::upsert(
                    $errorUpdates,
                    ['id'],
                    ['is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']
                );
            }
        });
    }
}
