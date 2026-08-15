<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class UserResource extends JsonResource
{
    public static $wrap = null;


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'user',
            'attributes' => [
                'is_auth' => auth()->check(),
                'is_verify_account' => (int)$this->is_verify_account,
                'restapi' => $this->restapi_key,
                'is_restapi' => $this->is_enabled_restapi,
                'name' => $this->name,
                'email' => $this->email,
                'email_security' => getEncryptedEmail($this->email),

                'phone' => $this->phone,
                'telegram' => $this->telegram,
                'created_at' => Carbon::parse($this->created_at)->diffForHumans(),
                'is_email_confirmed' => ! is_null($this->email_verified_at)
            ]
        ];
    }
}
