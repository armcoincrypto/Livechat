<?php

namespace iEXPackages\ExchangerApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PartnerExchangesResource extends JsonResource
{
    /**
     * Получаем результат массива
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => 'referral_user',
            'attributes' => [
                'created_at' => $this->created_at->toDateTimeString(),
                'order_id' => $this->id_task,
                'amount_in' => $this->tasks->give_price,
                'amount_out' => $this->tasks->receiving_price,
                'rate' => $this->tasks->course_display,
                'status_order' => $this->tasks->status == 4 ? 1 : 0,
                'partner_bonus' => $this->bonus,
                'partner_percent' => $this->current_percent,
            ],
        ];
    }
}
