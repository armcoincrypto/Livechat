<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Contact;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ContactValueResources extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection
            ->map(function ($contact) {
                return [
                    'id' => (int) $contact->id,
                    'name' => $contact->name,
                    'url' => $contact->url,
                    'block_size' => (int) ($contact->block_size ?? 0),
                    'is_home' => (int) ($contact->is_home ?? 0),
                    'icon' => !empty($contact->icon)
                        ? sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.contact'), $contact->icon)
                        : null,
                    'value' => $contact->value,
                ];
            })
            ->values()
            ->all();
    }
}
