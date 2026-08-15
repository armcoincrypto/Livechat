<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property string|null $amount
 * @property string|null $txid
 * @property string|null $address
 * @property int $confirmations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereConfirmations($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereTxid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletTransaction withoutTrashed()
 * @mixin \Eloquent
 */
class WalletTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'id_task',
        'address',
        'amount',
        'confirmations',
        'txid',
    ];
}
