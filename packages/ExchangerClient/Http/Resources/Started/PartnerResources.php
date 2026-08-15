<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Contact;
use App\Models\Menu;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class PartnerResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (Partner $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'image' => '/storage/' . $item->logo,
                    'link' => $item->link,
                ]
            ];
        });
    }
}
