<?php

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @property \Illuminate\Pagination\LengthAwarePaginator $resource
 */
class PaymentSystemsResources extends ResourceCollection
{
    private array $pagination;

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

        $resource = $resource->getCollection();
        parent::__construct($resource);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'logo' => $item->logo,
                        'logo_path' => $item->logo
                            ? '/' . trim(config('image.folders.payment_systems'), '/\\') . '/' . $item->logo
                            : null,
                        'locales' => $item->getTranslations(),
                        'created_at' => optional($item->created_at)->translatedFormat('d M Y H:i'),
                        'created_at_human' => optional($item->created_at)->diffForHumans(),
                        'updated_at' => optional($item->updated_at)->translatedFormat('d M Y H:i'),
                        'updated_at_human' => optional($item->updated_at)->diffForHumans(),
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
