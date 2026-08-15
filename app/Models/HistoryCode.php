<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property string|null $amount
 * @property string|null $currency
 * @property string|null $code
 * @property string|null $provider_id
 * @property string|null $type
 * @property int $id_pay
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode query()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereIdPay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryCode whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HistoryCode extends Model
{
    protected $table = 'histories_codes';

    protected $fillable = [
        'id_task',
        'code',
        'provider_id',
        'id_pay',
        'amount',
        'currency',
        'type',
    ];
}
