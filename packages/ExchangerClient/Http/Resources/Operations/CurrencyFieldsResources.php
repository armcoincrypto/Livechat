<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources\Operations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class CurrencyFieldsResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function ($item)
        {
            if ($item->type_field == 0) {
                return [
                    'key' => $item->key_id,
                    'type' => 'input',
                    'templateOptions' => [
                        'label' => $item->name,
                        'placeholder' => !empty($item->example) ? $item->example : null,
                        'required' => (bool) $item->obligatory_field == 0,
                        'appearance' => 'fill',
                        'description' => (is_string($item->description_field) && !empty($item->description_field)) ? $item->description_field : null
                    ],
                ];
            } else {
                $list_text = preg_replace('~[\r\n]+~', '', $item->list_text);
                $explode_list = explode(',', $list_text);

                $list_options = [];
                foreach ($explode_list as $key => $value) {
                    $list_options[] = [
                        'value' => $value,
                        'label' => $value,
                    ];
                }

                return [
                    'key' => $item->key_id,
                    'type' => ($item->type_field == 1) ? 'select' : 'radio',
                    'templateOptions' => [
                        'label' => $item->name ?? null,
                        'options' => $list_options,
                        'required' => (bool) $item->obligatory_field == 0,
                        'appearance' => 'fill',
                    ],
                ];
            }
        });
    }
}
