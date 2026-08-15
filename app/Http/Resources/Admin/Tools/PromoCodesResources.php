<?php
declare(strict_types=1);

namespace App\Http\Resources\Admin\Tools;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PromoCodesResources extends ResourceCollection
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
                    'attributes' => [
                        'name' => $item->name,
                        'status' => $item->status,
                        // count_uses: null means unlimited
                        'count_uses' => $item->count_uses === null ? null : (int) $item->count_uses,
                        'used' => $item->used,
                        // New promo codes schema: discount_type and discount_value
                        'discount_type' => (string) ($item->discount_type ?? 'percent'),
                        'discount_value' => (string) ($item->discount_value ?? '0'),
                        'scope_mode' => (string) ($item->scope_mode ?? 'all'),
                        'code' => $item->code,
                        'started_at' => $item->started_at ? Carbon::parse($item->started_at)->translatedFormat('d M Y') : null,
                        'expired_at' => $item->expired_at ? Carbon::parse($item->expired_at)->translatedFormat('d M Y') : null,
                        // Performance counts for directions (list view)
                        'directions_included_count' => (int) DB::table('promo_code_directions')->where('promo_code_id', (int) $item->id)->where('mode', 'include')->count(),
                        'directions_excluded_count' => (int) DB::table('promo_code_directions')->where('promo_code_id', (int) $item->id)->where('mode', 'exclude')->count(),
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
