<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Contact;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ContactGroupResources extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection
            ->map(function ($item) use ($request) {
                return [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'values' => isset($item->contacts)
                        ? (new ContactValueResources($item->contacts))->toArray($request)
                        : [],
                ];
            })
            ->values()
            ->all();
    }
}
