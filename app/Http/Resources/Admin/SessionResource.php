<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SessionResource  extends JsonResource
{
    public static $wrap = null;


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'user',
            'attributes' => [
                'name' => $this->name,
                'email' => $this->email,
                'avatar' => \Str::upper(\Str::substr($this->name, 0, 1)),
                'created_at' => Carbon::parse($this->created_at)->diffForHumans(),
                'permissions' => $this->resource->getAllPermissions()->pluck('name')->toArray(),
                'roles' => $this->resource->roles->pluck('title')->toArray(),
            ]
        ];
    }
}
