<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OrderArchivesResources extends ResourceCollection
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
                $archivedAt = $item->archived_at ?? null;
                if ($archivedAt instanceof CarbonInterface === false) {
                    if ($archivedAt) {
                        try {
                            $archivedAt = Carbon::parse($archivedAt);
                        } catch (\Throwable $e) {
                            $archivedAt = null;
                        }
                    } else {
                        $archivedAt = null;
                    }
                }
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'public_id' => $item->public_id,
                        'tech_name' => $item->direction_exchange?->tech_name,
                        'designation_xml' => $item->designation_xml,
                        'archived_at' => $archivedAt ? $archivedAt->format('c') : null
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
