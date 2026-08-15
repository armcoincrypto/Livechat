<?php

namespace App\Http\Resources\Admin\Tools;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PagesResources extends ResourceCollection
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

        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item)
            {
                $response =  [
                    'id' => $item->page_id,
                    'attributes' => [
                        'title' => $item->page_title,
                        'content' => $item->page_content,
                        'slug' => $item->page_slug,
                        'group_id' => $item->group_id !== null ? (int) $item->group_id : null,
                        'sort_order' => (int) ($item->sort_order ?? 0),
                        'is_active' => (bool) ($item->is_active ?? true),
                        'created_at' => $item->created_at->format('c'),
                        'updated_at' => $item->updated_at->format('c'),
                        'full_url' => config('app.frontend_url'). '/pages/'.$item->page_slug
                    ]
                ];

                if($item->user_id > 0) {
                    $response['attributes']['user'] = [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                    ];
                }

                return $response;
            }),
            ...$this->pagination
        ];
    }
}
