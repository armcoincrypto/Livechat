<?php
declare(strict_types=1);

namespace App\Http\Resources;

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
        $currentDiscountPercent = 0;
        if ((int) iEXSetting('is_discount_disabled') == 0) {
            $currentDiscountPercent = (isset($this->rewardProgram) ? $this->rewardProgram->percent : 0);
        }

        $minFill = 4;

        return [
            'id' => $this->id,
            'type' => 'user',
            'attributes' => [
                'is_auth' => auth()->check(),
                'is_verify_account' => (int) $this->is_verify_account,
                'restapi' => $this->restapi_key,
                'is_restapi' => $this->is_enabled_restapi,
                'name' => $this->name,
                'email' => $this->email,
                'email_security' => preg_replace_callback(
                    '/^(.)(.*?)([^@]?)(?=@[^@]+$)/u',
                    function ($m) use ($minFill) {
                        return $m[1]
                            .str_repeat('*', max($minFill, mb_strlen($m[2], 'UTF-8')))
                            .($m[3] ?: $m[1]);
                    },
                    $this->email
                ),

                'phone' => $this->phone,
                'telegram' => $this->telegram,
                'created_at' => Carbon::parse($this->created_at)->diffForHumans(),
                'is_email_confirmed' => ! is_null($this->email_verified_at),
                'referral_balance' => $this->user_balance->balance,
                'referral_hash' => (isset($this->referralLink)) ? $this->referralLink->getHashAttribute() : null,
                'referral_hash_url' => (isset($this->referralLink)) ? config('app.frontend_url').'/?ref='.$this->referralLink->getHashAttribute() : null,
                'personal_discount' => $currentDiscountPercent,

                'order' => [
                    'count' => $this->order_num ?? 0,
                    'sum' => $this->order_total_exchanges ?? 0,
                ],

                'meta' => [
                    'remote_ip' => $request->ip(),
                ],
            ]
        ];
    }
}
