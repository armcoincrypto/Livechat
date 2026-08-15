<?php

namespace App\Http\Resources\Admin\Others;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class APIResources extends ResourceCollection
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
                        'name'         => $item->name,
                        'email'        => $item->email,
                        'restapi_key'  => $item->restapi_key,
                        'tokens_count' => $item->tokens_count ?? ($item->tokens ? $item->tokens->count() : 0),
                        // Выводим до 5 последних токенов, помечая самый новый как активный
                        'tokens'       => (function () use ($item) {
                            if (! isset($item->tokens) || $item->tokens->count() === 0) {
                                return [];
                            }

                            return $item->tokens->values()->map(function ($token, int $index) {
                                $abilities = $token->abilities ?? [];

                                return [
                                    'id'             => $token->id,
                                    'name'           => $token->name,
                                    'token'          => $token->token,
                                    'token_code'     => $token->token_code,
                                    'abilities'      => $abilities,
                                    'is_full_access' => is_array($abilities) && in_array('*', $abilities, true),
                                    'last_used_at'   => $token->last_used_at ? $token->last_used_at->toIso8601String() : null,
                                    'expires_at'     => $token->expires_at ? $token->expires_at->toIso8601String() : null,
                                    'created_at'     => $token->created_at ? $token->created_at->toIso8601String() : null,
                                    'updated_at'     => $token->updated_at ? $token->updated_at->toIso8601String() : null,
                                    'is_expired'     => $token->expires_at ? $token->expires_at->isPast() : false,
                                    'is_unlimited'   => $token->expires_at === null,
                                    // Первый элемент коллекции — самый новый токен → активный
                                    'is_active'      => $index === 0,
                                ];
                            })->toArray();
                        })(),
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}

