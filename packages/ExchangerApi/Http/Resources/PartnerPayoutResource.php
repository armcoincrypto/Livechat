<?php

namespace iEXPackages\ExchangerApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use JetBrains\PhpStorm\ArrayShape;

class PartnerPayoutResource extends JsonResource
{
    /**
     * Получаем результат массива
     */
    #[ArrayShape(['id' => 'mixed', 'type' => 'string', 'attributes' => 'array'])]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => 'payout',
            'attributes' => [
                'created_at' => $this->created_at->toDateTimeString(),
                'status' => $this->status,
                'account' => $this->score,
                'pay_amount' => $this->base_referral,
                'pay_currency_name' => $this->currency->payment->name,
                'pay_currency_code' => $this->currency->code_currency->name,
            ],
        ];
    }
}
