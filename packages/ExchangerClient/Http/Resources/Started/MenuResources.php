<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Contact;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class MenuResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (Menu $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'color' => $item->color,
                    'slug' => $item->slug,
                    'children' => $item->children->map(function (Menu $child) {
                        return  [
                            'id' => $child->id,
                            'attributes' => [
                                'name' => $child->name,
                                'slug' => $child->slug,
                                'color' => $child->color,
                            ]
                        ];
                    })
                ]
            ];
        });
    }
}
