<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

final class OrderChatResources extends ResourceCollection
{
    private int $publicId = 0;

    public function withPublicId(int $publicId): self
    {
        $this->publicId = $publicId;

        return $this;
    }

    public function toArray($request): array
    {
        $publicId = $this->publicId;

        return $this->collection->map(function ($item) use ($publicId) {
            $file = null;
            if (!empty($item->file_path) && $publicId > 0) {
                $file = '/apis/client-api/v1/order/'.$publicId.'/chat/files/'.$item->file_path;
            }

            return [
                'message'   => $item->message,
                'date'      => Carbon::parse($item->created_at)->diffForHumans(),
                'type_user' => $item->type_user,
                'file'      => $file,
            ];
        })->all();
    }
}
