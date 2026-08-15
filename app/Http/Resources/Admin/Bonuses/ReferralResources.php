<?php

namespace App\Http\Resources\Admin\Bonuses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Settings\ReferralConfig;

class ReferralResources extends ResourceCollection
{
    private array $pagination;
    private int $defaultProgramId;

    public function __construct($resource)
    {
        // Получаем ID дефолтной программы один раз
        /** @var ReferralConfig $config */
        $config = app(ReferralConfig::class);
        $this->defaultProgramId = (int) $config->defaultReferralProgramId();

        $this->pagination = [
            'total' => $resource->total(),
            'per_page' => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'from' => $resource->firstItem(),
            'to' => $resource->lastItem(),
            'last_page' => $resource->lastPage(),
        ];

        // Убираем meta/links пагинатора
        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'lifetime_minutes' => $item->lifetime_minutes,
                        'title' => $item->title,
                        'description' => $item->description,
                        'percent' => (string) $item->percent,

                        'is_default' => $item->id === $this->defaultProgramId,

                        'created_at' => $item->created_at?->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at?->diffForHumans(),
                        'updated_at' => $item->updated_at?->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at?->diffForHumans(),
                    ],
                ];
            }),
            ...$this->pagination,
        ];
    }
}
