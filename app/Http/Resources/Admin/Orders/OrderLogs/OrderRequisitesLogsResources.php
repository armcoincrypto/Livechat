<?php

namespace App\Http\Resources\Admin\Orders\OrderLogs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderRequisitesLogsResources extends ResourceCollection
{
    /**
     * @var array<string,int|null>
     */
    private array $pagination;

    public function __construct($resource)
    {
        $this->pagination = [
            'total' => (int) $resource->total(),
            'per_page' => (int) $resource->perPage(),
            'current_page' => (int) $resource->currentPage(),
            'from' => $resource->firstItem(),
            'to' => $resource->lastItem(),
            'last_page' => (int) $resource->lastPage(),
        ];

        parent::__construct($resource->getCollection());
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(static function ($item) {
                $ext = (array) ($item->ext_params ?? []);

                // Исторически в модели было отношение user(), но корректнее manager().
                // Поддерживаем оба варианта, чтобы ресурс не ломался при старых моделях.
                $manager = $item->manager ?? null;

                return [
                    'id' => (int) $item->id,
                    'attributes' => [
                        'manager' => [
                            'id' => $manager?->id,
                            'name' => $manager?->name,
                            'email' => $manager?->email,
                        ],
                        'wallet_number' => (string) ($item->wallet_number ?? ''),
                        'ip_address' => (string) ($item->ip_address ?? ''),
                        'id_task' => (int) ($item->id_task ?? 0),
                        'ext_params' => $ext,
                        'created_at' => $item->created_at?->format('c'),
                    ],
                ];
            }),
            ...$this->pagination,
        ];
    }
}
