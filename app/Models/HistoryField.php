<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_currency1
 * @property int $id_currency2
 * @property string|null $filed_give
 * @property string|null $filed_receiving
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField query()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereFiledGive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereFiledReceiving($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereIdCurrency1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereIdCurrency2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryField whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HistoryField extends Model
{
    protected $table = 'history_fields';

    protected $fillable = [
        'id_user',
        'id_currency1',
        'id_currency2',
        'filed_give',
        'filed_receiving',
    ];
}
