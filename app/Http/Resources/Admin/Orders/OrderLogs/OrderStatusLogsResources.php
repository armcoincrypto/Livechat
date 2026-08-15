<?php

namespace App\Http\Resources\Admin\Orders\OrderLogs;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderStatusLogsResources extends ResourceCollection
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
            'data' => $this->collection->map(function ($item)
            {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'id_task' => (int) $item->id_task,
                        'direction_exchange' => [
                            'id' => $item->id_direction_exchange !== null ? (int) $item->id_direction_exchange : null,
                            'tech_name' => $item->directionExchange?->tech_name ?? null,
                        ],

                        'user' => [
                            'id' => $item->user?->id,
                            'name' => $item->user?->name,
                            'email' => $item->user?->email,
                        ],

                        'old_status' => [
                            'id' => (int) $item->old_status,
                            'name' => $item->oldStatus?->name ?? '',
                        ],
                        'new_status' => [
                            'id' => (int) $item->new_status,
                            'name' => $item->newStatus?->name ?? '',
                        ],

                        // Старые поля (совместимость)
                        'in_price' => $item->in_price,
                        'out_price' => $item->out_price,
                        'course_display' => $item->course_display,

                        // Новые нормализованные поля
                        'in_amount' => $item->in_amount,
                        'out_amount' => $item->out_amount,
                        'course_display_text' => $item->course_display_text,
                        'course_display_rate' => $item->course_display_rate,
                        'course_float_rate' => $item->course_float_rate,
                        'course_diff_percent' => $item->course_diff_percent,

                        // Доп. контекст (если нужно фронту)
                        'task' => [
                            'id' => $item->task?->id,
                            'public_id' => $item->task?->public_id,
                            'status' => $item->task?->status,
                        ],

                        'created_at' => $item->created_at?->format('c'),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
