<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_currency
 * @property string|null $wallet
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets query()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawalWallets whereWallet($value)
 * @mixin \Eloquent
 */
class WithdrawalWallets extends Model
{
    protected $table = 'withdrawal_wallets';

    protected $fillable = [
        'id_user',
        'wallet',
        'id_currency',
    ];
}
