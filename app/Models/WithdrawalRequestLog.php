<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_withdrawal_request
 * @property int $id_manager
 * @property string $text
 * @property string $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $remainder
 * @property-read \App\Models\User|null $manager
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereIdWithdrawalRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereRemainder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalRequestLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class WithdrawalRequestLog extends Model
{
    protected $table = 'withdrawal_request_log';

    protected $fillable = [
        'id_withdrawal_request', 'text', 'amount', 'remainder', 'id_manager',
    ];

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }
}
