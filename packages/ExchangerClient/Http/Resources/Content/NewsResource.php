<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attributes' => [
                'title' => $this->name,
                'category' => [
                    'name' => isset($this->category) ? $this->category->name : 'News',
                    'color' => isset($this->category) ? $this->category->color : '',
                ],
                'slug' => sprintf('%s-%s', $this->id, $this->parent_url),
                'body' => $this->text,
                'parent_url' => $this->parent_url,
                'image' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.news'), $this->image),
                'created_at' => Carbon::parse($this->created_at)->timestamp * 1000,
            ]
        ];
    }
}
