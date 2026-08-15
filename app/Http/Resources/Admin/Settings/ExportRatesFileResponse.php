<?php

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ExportRatesFileResponse extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'filename' => $item->filename,
                        'type_file' => $item->type_file,
                        'status' => (bool)$item->status
                    ]
                ];
            })
        ];
    }
}

