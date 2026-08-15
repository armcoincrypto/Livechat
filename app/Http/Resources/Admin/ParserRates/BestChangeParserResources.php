<?php
declare(strict_types=1);

namespace App\Http\Resources\Admin\ParserRates;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * BestChangeParserResources
 *
 * Коллекция для списка bestchange_directions с пагинацией.
 */
final class BestChangeParserResources extends ResourceCollection
{
    /**
     * Пагинатор исходного запроса.
     */
    private LengthAwarePaginator $paginator;

    /**
     * @param LengthAwarePaginator $resource Пагинатор Eloquent.
     */
    public function __construct(LengthAwarePaginator $resource)
    {
        // ResourceCollection умеет работать с пагинатором, но нам важно:
        // 1) отдавать pagination-мета в нашем формате
        // 2) не тащить стандартные meta/links
        // Поэтому сохраняем пагинатор отдельно и передаём в parent только коллекцию элементов.
        $this->paginator = $resource;

        parent::__construct($resource->getCollection());

        // Сохраняем исходный ресурс для совместимости (если кто-то обращается к $this->resource).
        $this->resource = $resource;
    }

    public function toArray(Request $request): array
    {
        $paginator = $this->paginator;

        $decimalPlaces = (int) iEXSetting('display_decimal_places', 10);

        $data = $this->collection->map(static function ($item) use ($decimalPlaces): array {
            $rateMode = (string) ($item->rate_mode ?? 'position');
            if (!in_array($rateMode, ['position', 'median_top_n', 'weighted_avg_top_n'], true)) {
                $rateMode = 'position';
            }

            // top_n имеет смысл только для стратегий TOP-N.
            $topN = null;
            if ($rateMode !== 'position') {
                $n = (int) ($item->top_n ?? 0);
                $topN = $n > 0 ? $n : null;
            }

            return [
                'id' => (int) $item->id,
                'attributes' => [
                    'direction' => [
                        'id' => $item->direction_exchange?->id,
                        'name' => $item->direction_exchange?->tech_name,
                        'status' => $item->direction_exchange?->status,
                    ],

                    'code' => $item->code,
                    'status' => (bool) $item->status,

                    // стратегия выбора курса (NEW)
                    'rate_mode' => $rateMode,
                    'top_n' => $topN,

                    'position_num' => (string) $item->position_num,
                    'step' => (string) $item->step,

                    'rate_value_without_step' => removeTrailingZeros(
                        iex_number_format((float) $item->rate_value_without_step, $decimalPlaces)
                    ),
                    'rate_value' => removeTrailingZeros(
                        iex_number_format((float) $item->rate_value, $decimalPlaces)
                    ),
                    'decimal' => $decimalPlaces,

                    'is_error_parser' => (int) $item->is_error_parser,
                    'source_name' => (string) ($item->source_name ?? ''),

                    'id_currency_in' => (int) $item->id_currency_in,
                    'id_currency_out' => (int) $item->id_currency_out,
                    'city_id' => (int) $item->city_id,

                    'is_favorite' => (bool) $item->is_favorite,
                    'direction_exchanges' => $item->direction_exchange,

                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ],
            ];
        })->values()->all();

        return [
            'data' => $data,

            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
