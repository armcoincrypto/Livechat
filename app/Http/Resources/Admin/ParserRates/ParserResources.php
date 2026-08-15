<?php

namespace App\Http\Resources\Admin\ParserRates;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class ParserResources extends ResourceCollection
{
    private array $pagination;

    public function __construct($resource)
    {
        $this->pagination = [
            'total'        => $resource->total(),
            'per_page'     => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'from'         => $resource->firstItem(),
            'to'           => $resource->lastItem(),
            'last_page'    => $resource->lastPage(),
        ];

        // Извлекаем коллекцию моделей из пагинатора
        $resource = $resource->getCollection();

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
                        'name'          => $item->name,
                        'status'        => (bool) $item->status,
                        'code'          => $item->code,
                        'type'          => $item->type,
                        'is_not_update' => $item->is_not_update,
                        'summa'         => removeTrailingZeros(
                            iex_number_format((float) $item->summa, (int) $item->number_format)
                        ),
                        'decimal'       => (int) $item->number_format,
                        'direction_exchanges' => $item->direction_exchange,
                        'updated_at'    => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),

                        // Лёгкая аналитика по истории курсов (используем уже загруженный relation)
                        'changes_24h'        => $changesCount,
                        'change_24h_percent' => $percentChange,
                    ],
                ];
            }),
            ...$this->pagination,
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
