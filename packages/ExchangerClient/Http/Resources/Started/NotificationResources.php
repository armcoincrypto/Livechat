<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Menu;
use App\Models\NoticeExchange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class NotificationResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (NoticeExchange $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'text' => $item->text,
                    'bg_color' => !empty($item->bg_color) ? '#'.$item->bg_color : '',
                    'text_color' => !empty($item->text_color) ? '#'.$item->text_color : '',
                    'icon_notice' => $item->icon_notice ? '/storage/notices/'.$item->icon_notice : '',
                    'text_size' => $item->text_size,
                    'link' => $item->link,
                    'is_blank' => $item->is_blank,
                ]
            ];
        });
    }
}
