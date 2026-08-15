<?php

namespace App\Http\Resources\Admin\ParserRates;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class FormulaParserResources extends ResourceCollection
{
    private $pagination;


    public function __construct($resource)
    {
        $this->pagination = [
            'total' => $resource->total(),
            'per_page' => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'from' => $resource->firstItem(),
            'to' => $resource->lastItem(),
            'last_page' => $resource->lastPage(),
        ];

        $resource = $resource->getCollection(); // Necessary to remove meta and links

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {

                $logs = $item->ratesHistoryLogs ?? collect();

                // Общее количество логов за 24 часа (любые изменения)
                $changesCount = $logs->count();
                $percentChange = null;

                // Берём только те логи, где new_value действительно число
                $numericRates = $logs
                    ->map(function ($log) {
                        return $this->toNumericString($log->new_value ?? null);
                    })
                    ->filter() // убираем null
                    ->values();

                $numericCount = $numericRates->count();

                if ($numericCount > 1) {
                    $firstRate = $numericRates->first();
                    $lastRate  = $numericRates->last();

                    if ($firstRate !== null && $lastRate !== null && bccomp($firstRate, '0', 18) !== 0) {
                        $diff = bcsub($lastRate, $firstRate, 8); // разница
                        $ratio = bcdiv($diff, $firstRate, 8);    // относительное изменение
                        $percentChange = (float) bcmul($ratio, '100', 2); // в процентах
                    }
                } elseif ($numericCount === 1) {
                    // За 24 часа одно числовое значение — считаем, что изменения 0%
                    $percentChange = 0.0;
                }


                return [
                    'id' => $item->id,
                    'attributes' => [
                        'title' => $item->title,
                        'name' => html_entity_decode($item->name),
                        'status' => (bool)$item->status,
                        'exchange_in' => $item->exchange_in,
                        'exchange_out' => $item->exchange_out,
                        'value' => $item->value,
                        'is_error_update' => (int) $item->is_error_update,
                        'summa' => removeTrailingZeros(iex_number_format((float)$item->summa, (int)$item->number_format)),
                        'decimal' => $item->number_format,
                        'direction_exchange' => $item->direction_exchange->count() > 0 ? $item->direction_exchange->map(function($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->tech_name
                            ];
                        }) : [],
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),

                        // Лёгкая аналитика по истории курсов (используем уже загруженный relation)
                        'changes_24h'        => $changesCount,
                        'change_24h_percent' => $percentChange,
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }

    /**
     * Приводит значение к числовой строке или возвращает null, если это не число.
     */
    protected function toNumericString(?string $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }
}
