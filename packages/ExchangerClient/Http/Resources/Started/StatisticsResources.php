<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Contact;
use App\Models\InfoStatistic;
use App\Models\Menu;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class StatisticsResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (InfoStatistic $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'value' => $item->value,
                    'link' => $item->link,
                    'image' => (!empty($item->image) ? '/storage/statistics/'.$item->image : ''),
                ]
            ];
        });
    }
}
