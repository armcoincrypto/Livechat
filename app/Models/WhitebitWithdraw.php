<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $method_type
 * @property int $id_task
 * @property string|null $address
 * @property string|null $amount
 * @property string|null $currency
 * @property string|null $ticker
 * @property string|null $fee
 * @property int $status
 * @property string|null $transaction_hash
 * @property string|null $unique_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw query()
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereMethodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereTicker($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereTransactionHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereUniqueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhitebitWithdraw whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class WhitebitWithdraw extends Model
{
    protected $table = 'whitebit_withdraw';

    protected $fillable = [
        'method_type',
        'id_task',
        'address',
        'amount',
        'currency',
        'ticker',
        'fee',
        'status',
        'transaction_hash',
        'unique_id',
    ];
}
