<?php

namespace iEXPackages\ExchangerApi\Http\Resources\Account;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountInfoResource extends JsonResource
{
    /**
     * Информация о пользователе
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'type' => 'user',
            'attributes' => [
                'name' => (string) $this->name,
                'email' => (string) $this->email,
                'locale' => (string) $this->language,
                'created_at' => (int) $this->created_at->timestamp,
                'last_visit' => (int) $this->last_activity_at->timestamp,
            ],
        ];
    }
}
