<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class OrderAttachedFileResources extends ResourceCollection
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
                'file' => $item->file,
                'file_path' => '/storage/orders/' . $item->file,
                'text' => $item->text,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'user' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name
                ]
            ];
        });
    }
}
