<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Banner;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class BannerResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (Banner $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->title,
                    'text' => $item->text,
                    'icon' => !empty($item->images) ?  '/storage/banners/' .$item->images : null,
                    'color_title' => '#' . $item->color_title,
                    'color_text' => '#'. $item->color_text,
                    'banner_image' => !empty($item->images_banner) ? '/storage/banners/' .$item->images_banner : null,
                    'buttons' => $item->buttons->map(function ($button) {
                        return [
                            'id' => $button->id,
                            'attributes' => [
                                'name' => $button->name,
                                'link' => $button->link,
                                'color_bg' => '#' .$button->color_bg_button,
                                'color_text' => '#'. $button->color_text_button,
                            ]
                        ];
                    }),
                ]
            ];
        });
    }
}
