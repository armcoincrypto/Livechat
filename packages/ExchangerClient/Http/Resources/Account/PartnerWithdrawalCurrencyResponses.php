<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class PartnerWithdrawalCurrencyResponses extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {

        return $this->collection->map(function ($item)
        {
            // Список
            //$autoCompleteWallets = WithdrawalWallets::where('id_currency', $item->id)->where('id_user', \auth()->id())->pluck('wallet');

            return [
                'id' => $item->id,
                'name' => sprintf('%s %s', (isset($item->payment)) ? $item->payment->name : '', $item->code_currency->name),
                'autoCompleteWallets' => [],
                'mask' => $item->mask_account_from,
                'icon_url' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.payment_systems'), $item?->payment->logo),
                'form' => [
                    'placeholder' => $item->first_char,
                    'default_value' => $item->char_default,
                    'min_char' => $item->min_char,
                    'max_char' => $item->max_char,
                ],
                'fees' => [
                    'fee_percent' => $item->payout_commission,
                    'fee_currency' => $item->payout_commission_amount,
                ],
            ];
        });
    }
}
