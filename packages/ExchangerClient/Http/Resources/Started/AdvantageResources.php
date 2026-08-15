<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Advantage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class AdvantageResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (Advantage $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->title,
                    'content' => $item->content,
                    'icon' => '/storage/advantage/' .$item->icon,
                    'link' => $item->link,
                    'is_target' => (int)$item->is_target,
                    'rowspan' => (int)$item->rowspan,
                    'colspan' => (int)$item->colspan
                ]
            ];
        });
    }
}
