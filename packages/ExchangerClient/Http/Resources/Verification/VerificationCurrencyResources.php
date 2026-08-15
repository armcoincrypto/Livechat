<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Verification;

use Illuminate\Http\Resources\Json\ResourceCollection;

class VerificationCurrencyResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->reject(fn($value) => !isset($value->payment) or !isset($value->code_currency))->map(function ($item)
        {
            return [
                'name' => sprintf('%s %s', $item->payment->name, $item->code_currency->name),
                'currency' => $item->id,
                'mask' => $item->mask_account_from,
                'icon_url_path' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.payment_systems'), $item->payment->logo),
                'form' => [
                    'default_value' => $item->char_default,
                    'min_char' => $item->min_char,
                    'max_char' => $item->max_char,
                ],
            ];
        });
    }
}
