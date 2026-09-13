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

            $dir = $item->direction_exchange;
            $fromCode = strtoupper((string) ($dir?->currency1?->designation_xml ?? ''));
            $toCode = strtoupper((string) ($dir?->currency2?->designation_xml ?? ''));
            $pair = ($fromCode !== '' && $toCode !== '') ? $fromCode.'→'.$toCode : '';
            $techName = (string) ($dir?->tech_name ?? '');
            $displayName = $pair !== ''
                ? ($techName !== '' && $techName !== $pair ? $pair.' · '.$techName : $pair)
                : $techName;
            $pricingOwner = trim((string) ($dir?->parser_source_name ?? ''));
            $mappingActive = (bool) $item->status;
            $ownerIsBestChange = $pricingOwner === 'BestChange';
            $authoritative = $mappingActive && $ownerIsBestChange;
            $sourcePos = trim((string) ($item->position_num ?? ''));
            $displayHint = null;
            if (! $mappingActive && (int) $item->is_error_parser === 1) {
                $displayHint = $ownerIsBestChange
                    ? 'Маппинг выключен · ошибка парсера (не влияет, пока status=0)'
                    : 'Неавторитетный маппинг BestChange · владелец курса: '
                        . ($pricingOwner !== '' ? $pricingOwner : 'другой')
                        . ' · ошибка парсера не определяет live-курс';
            } elseif (! $mappingActive) {
                $displayHint = $ownerIsBestChange
                    ? 'Маппинг выключен · позиция сохранена как настройка'
                    : 'Неавторитетный маппинг · live-курс не из BestChange';
            } elseif ($authoritative) {
                $displayHint = 'Источник: позиция #' . ($sourcePos !== '' ? $sourcePos : '—')
                    . ' · внешняя цель Top-N: #' . ($sourcePos !== '' ? $sourcePos : '—');
            }

            return [
                'id' => (int) $item->id,
                'attributes' => [
                    'direction' => [
                        'id' => $dir?->id,
                        'name' => $displayName,
                        'tech_name' => $techName,
                        'from_code' => $fromCode !== '' ? $fromCode : null,
                        'to_code' => $toCode !== '' ? $toCode : null,
                        'pair' => $pair !== '' ? $pair : null,
                        'status' => $dir?->status,
                        'parser_source_name' => $pricingOwner,
                    ],

                    'code' => $item->code,
                    'status' => $mappingActive,

                    // стратегия выбора курса (NEW)
                    'rate_mode' => $rateMode,
                    'top_n' => $topN,

                    'position_num' => (string) $item->position_num,
                    'source_position' => $sourcePos !== '' ? (int) $sourcePos : null,
                    'external_target_position' => $sourcePos !== '' ? (int) $sourcePos : null,
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
                    'pricing_owner' => $pricingOwner,
                    'mapping_authoritative' => $authoritative,
                    'display_status_hint' => $displayHint,

                    'id_currency_in' => (int) $item->id_currency_in,
                    'id_currency_out' => (int) $item->id_currency_out,
                    'city_id' => (int) $item->city_id,

                    'is_favorite' => (bool) $item->is_favorite,
                    'direction_exchanges' => $dir,

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
