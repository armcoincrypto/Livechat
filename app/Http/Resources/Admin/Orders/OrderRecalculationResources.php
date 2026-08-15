<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class OrderRecalculationResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'course' => $item->course,
                    'course_value' => $item->course_value,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                ]
            ];
        });
    }
}
