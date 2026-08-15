<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Verification;

use iEXPackages\ExchangerClient\Support\MaskVerificationIdentifier;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

class VerificationCardResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->reject(fn($value) => !isset($value->currency))->map(function ($item)
        {
            return [
                'id' => $item->id,
                'type' => 'card_verification',
                'attributes' => [
                    'public_id' => $item->id,
                    'created_at' => $item->created_at->format('c'),
                    'name' => Str::upper($item->name),
                    'card_number' => MaskVerificationIdentifier::mask((string) ($item->resolvedCardNumber() ?? '')),
                    'status' => $item->status,
                    'id_bank' => $item->id_currency,
                ],

                'relationships' => [
                    'issuing_bank' => [
                        'name' => sprintf('%s %s', $item->currency->payment->name, $item->currency->code_currency->name),
                        'icon_url_path' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.payment_systems'),  $item->currency->payment->logo),
                    ],
                ],
            ];
        });
    }
}
