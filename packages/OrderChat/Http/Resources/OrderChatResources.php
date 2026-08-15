<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

final class OrderChatResources extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection->map(function ($item) {
            return [
                'message'   => $item->message,
                'date'      => Carbon::parse($item->created_at)->diffForHumans(),
                'type_user' => $item->type_user,
                'file'      => $item->file_path,
            ];
        })->all();
    }
}
