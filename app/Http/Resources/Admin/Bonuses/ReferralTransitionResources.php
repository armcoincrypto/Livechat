<?php

namespace App\Http\Resources\Admin\Bonuses;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

class ReferralTransitionResources extends ResourceCollection
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
                $firstVisit = $item->first_visit_at ? Carbon::parse($item->first_visit_at) : null;
                $lastVisit = $item->last_visit_at ? Carbon::parse($item->last_visit_at) : null;

                return [
                    // Используем ref_hash как идентификатор агрегированной записи
                    'id' => $item->ref_hash,
                    'attributes' => [
                        'ref_hash' => $item->ref_hash,
                        'total' => (int) $item->total,
                        'unique_ips' => (int) ($item->unique_ips ?? 0),

                        'referral_user' => $item->referral_user_id ? [
                            'id' => (int) $item->referral_user_id,
                            'name' => $item->referral_user_name,
                            'email' => $item->referral_user_email,
                        ] : null,

                        'first_visit_at' => $firstVisit?->translatedFormat('d M Y H:i'),
                        'first_visit_at_human' => $firstVisit?->diffForHumans(),

                        'last_visit_at' => $lastVisit?->translatedFormat('d M Y H:i'),
                        'last_visit_at_human' => $lastVisit?->diffForHumans(),
                    ],
                ];
            }),
            ...$this->pagination,
        ];
    }
}
