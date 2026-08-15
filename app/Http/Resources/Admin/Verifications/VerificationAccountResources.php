<?php

namespace App\Http\Resources\Admin\Verifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VerificationAccountResources extends ResourceCollection
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
            'data' => $this->collection->map(function ($item)
            {
                $fileOne = get_image_from_private_path('user_verification', $item->file_one);
                $fileTwo = get_image_from_private_path('user_verification', $item->file_two);

                return [
                    'id' => $item->id,
                    'attributes' => [
                        'id_user' => $item->user_id,
                        'user' => [
                            'name' => $item->user?->name,
                            'email' => $item->user?->email,
                        ],
                        'fio_user' => $item->fio_user,
                        'file_one' =>  $fileOne,
                        'file_one_preview' =>  empty($item->file_one_preview) ? $fileOne : get_image_from_private_path('user_verification', $item->file_one_preview),
                        'file_two' =>  $fileTwo,
                        'file_two_preview' =>  empty($item->file_two_preview) ? $fileTwo : get_image_from_private_path('user_verification', $item->file_two_preview),
                        'status' => $item->status,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
