<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

class OrderCommentResources extends ResourceCollection
{
    public function toArray($request) {
        return $this->collection->map(function ($item) {
            return [
                'id' => (int)$item->id,
                'class_style' => $item->class_style,
                'created_at' => Carbon::parse($item->created_at)->diffForHumans(),
                'message' => $item->message,
                'user' => [
                    'id' => $item->user?->id,
                    'name' => $item->user?->name,
                ]
            ];
        });
    }
}
