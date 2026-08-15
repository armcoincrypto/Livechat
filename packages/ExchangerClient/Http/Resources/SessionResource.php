<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDiscountActive = (int) iEXSetting('is_discount_disabled') === 0;

        if ($isDiscountActive) {
            $hasValidPersonalDiscount = is_numeric($this->personal_discount) && $this->personal_discount > 0;
            $personalDiscount = $hasValidPersonalDiscount
                ? (float) $this->personal_discount
                : (float) ($this->rewardProgram->percent ?? 0);
        } else {
            $personalDiscount = 0;
        }


        return [
            'id' => $this->id,
            'type' => 'user',
            'attributes' => [
                'is_auth' => !is_null($request->user()),
                'is_verify_account' => (int) $this->is_verify_account,
                'name' => $this->name,
                'email' => $this->email,
                'email_security' => getEncryptedEmail($this->email),
                'created_at' => Carbon::parse($this->created_at)->diffForHumans(),
                'is_email_confirmed' => !is_null($this->email_verified_at),
                'personal_discount' => $personalDiscount,
                'order' => [
                    'count' => (int) ($this->order_num ?? 0),
                    'sum' => (float) ($this->order_total_exchanges ?? 0),
                ],
            ]
        ];
    }
}
