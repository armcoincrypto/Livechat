<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class ContactResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (Contact $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'value' => $item->value,
                    'url' => $item->url,
                    'icon_url' => ! empty($item->icon) ? '/storage/contact/'.$item->icon : null,
                    'text_color' => $item->text_color,
                ]
            ];
        });
    }
}
