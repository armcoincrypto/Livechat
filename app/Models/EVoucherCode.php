<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property string|null $account
 * @property string|null $amount
 * @property string|null $batch_num
 * @property string|null $voucher_num
 * @property string|null $voucher_code
 * @property string|null $voucher_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode query()
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereBatchNum($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereVoucherAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereVoucherCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EVoucherCode whereVoucherNum($value)
 * @mixin \Eloquent
 */
class EVoucherCode extends Model
{
    protected $table = 'e_voucher_codes';

    protected $fillable = [
        'id_task',
        'account',
        'amount',
        'batch_num',
        'voucher_num',
        'voucher_code',
        'voucher_amount',
    ];
}
