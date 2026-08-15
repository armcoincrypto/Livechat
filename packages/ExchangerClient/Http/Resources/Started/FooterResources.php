<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\LinksFooterGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class FooterResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (LinksFooterGroup $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'links' => $item->links->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'attributes' => [
                                'name' => $child->name,
                                'url' => $child->url,
                                'is_blank' => $child->is_blank,
                            ]
                        ];
                    })->toArray()
                ]
            ];
        });
    }
}
