<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderBonusesResources extends ResourceCollection
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
                return [
                    'id' => $item->id,
                    'big_id' => $item->big_id,

                    'attributes' => [
                        'status' => $item->status,
                        'verified_at' => $item->verified_at,
                        'is_confirm' => is_null($item->verified_at) and iEXSetting('is_verified_payouts_bonus') == 1,
                        'manager' => [
                            'id' => $item->manager?->id,
                            'name' => $item->manager?->name,
                            'email' => $item->manager?->email,
                        ],
                        'ip' => $item->ip,
                        'currency' => [
                            'id' => (isset($item->currency)) ? $item->currency->id : '',
                            'name' => (isset($item->currency)) ? $item->currency->tech_name : '',
                            'code_name' => (isset($item->currency)) ? $item->currency->code_currency?->name : '',
                        ],

                        'amount' => (float)$item->view_balance_referral,
                        'balance_referral' => (float)$item->balance_referral,
                        'score' => $item->score,
                        'user' => [
                            'id' => $item->user?->id,
                            'name' => $item->user?->name,
                            'email' => $item->user?->email,
                        ],
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
