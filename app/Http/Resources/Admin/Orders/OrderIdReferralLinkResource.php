<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderIdReferralLinkResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'link' => $this->referral_link,
            'user' => [
                'id' => $this->referral_link->user->id,
                'name' => $this->referral_link->user->name,
                'email' => $this->referral_link->user->email,
            ],
            'log' => $this->referral_log
        ];
    }
}
