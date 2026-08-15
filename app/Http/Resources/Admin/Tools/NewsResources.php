<?php

namespace App\Http\Resources\Admin\Tools;

use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NewsResources extends ResourceCollection
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
        $categories = NewsCategory::orderBy('name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'color' => $item->color
            ];
        })->values();


        return [
            'data' => $this->collection->map(function ($item)
            {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'category_id' => $item->category_id,
                        'category_name' => isset($item->category) ? $item->category->name : 'News',
                        'name' => $item->name,
                        'views' => $item->views,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),
                    ]
                ];
            }),
            'categories' => $categories,
            ...$this->pagination
        ];
    }
}
