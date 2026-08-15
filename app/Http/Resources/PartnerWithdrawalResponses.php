<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PartnerWithdrawalResponses extends ResourceCollection
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
            // Если заявка выполнена
            if ($item->status == 1) {
                $sum = $item->view_balance_referral;
            } else {
                $sum = $item->balance_referral;
            }

            return [
                'id' => $item->id,
                'type' => 'referral_charge',
                'attributes' => [
                    'created_at' => Carbon::parse($item->created_at)->translatedFormat('d M Y H:i'),
                    'payout_status' => ($item->status == 0 ? 'unpaid' : 'paid'),
                    'charged_amount' => [
                        'amount' => iex_number_format($sum),
                        'currency' => $item->currency->code_currency->name,
                        'name' => sprintf('%s %s', $item->currency->payment->name, $item->currency->code_currency->name),
                    ],
                ],
            ];
        });
    }
}
