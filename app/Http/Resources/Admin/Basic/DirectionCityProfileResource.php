<?php

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class DirectionCityProfileResource extends JsonResource
{
    protected array $pagination = [];

    public function __construct($resource)
    {
        // Если нам передали LengthAwarePaginator, вытаскиваем пагинацию и коллекцию
        if ($resource instanceof LengthAwarePaginator) {
            $this->pagination = [
                'total'        => $resource->total(),
                'per_page'     => $resource->perPage(),
                'current_page' => $resource->currentPage(),
                'from'         => $resource->firstItem(),
                'to'           => $resource->lastItem(),
                'last_page'    => $resource->lastPage(),
            ];

            // Важно: заменить ресурс на коллекцию моделей, чтобы не было meta/links Laravel’а
            $resource = $resource->getCollection();
        }

        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        // Если это коллекция профилей => возвращаем data + meta
        if ($this->resource instanceof Collection) {
            return [
                'data' => $this->resource->map(function ($item) use ($request) {
                    return (new static($item))->toSingleArray($request);
                }),
                'meta' => $this->pagination,
            ];
        }

        // Если это одиночная модель => обычный формат
        return $this->toSingleArray($request);
    }

    /**
     * Описывает одну запись (один профиль).
     */
    protected function toSingleArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'attributes' => [
                'name'          => $this->name,
                'code'          => $this->code,
                'profit'        => $this->profit !== null ? (string) $this->profit : null,
                'profit_s'      => $this->profit_s !== null ? (string) $this->profit_s : null,
                'add_comm'      => $this->add_comm !== null ? (string) $this->add_comm : null,
                'status'        => (bool) $this->status,
                'cities_count'  => (int) ($this->pivot_cities_count ?? 0),
                'created_at'    => $this->created_at?->format('c'),
                'updated_at'    => $this->updated_at?->format('c'),
            ],
        ];
    }
}
