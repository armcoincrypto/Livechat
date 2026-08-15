<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property float|null $balance
 * @property int $id_code_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property float $referral_total_profit
 * @property float $referral_total_withdrawal
 * @property-read \App\Models\CodeCurrency|null $code_currency
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereIdCodeCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereReferralTotalProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereReferralTotalWithdrawal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class UserBalance extends Model
{
    protected $table = 'user_balance';

    protected $fillable = [
        'id_user',
        'id_code_currency',
        'balance',
        'referral_total_profit',
        'referral_total_withdrawal',
    ];

    public function code_currency()
    {
        return $this->hasOne(CodeCurrency::class, 'id', 'id_code_currency');
    }
}
