<?php

namespace App\Http\Resources\Admin\Others;

use App\Models\User;
use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class TotalExchangesResources extends ResourceCollection
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
                        'usd' => iex_number_format($item->usd, 2, true),
                        'users' => User::role(Role::all())->get()->map(function (User $user) use($item) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                                'count' => $user->managerHistory('count', $item->created_at)
                            ];
                        }),
                        'created_at' => Carbon::parse($item->created_at)->translatedFormat('d M Y'),
                        'created_at_human' => Carbon::parse($item->created_at)->diffForHumans()
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
