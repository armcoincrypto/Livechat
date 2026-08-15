<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_internal_account
 * @property int $id_user
 * @property int $id_task
 * @property int $type_history
 * @property string|null $description
 * @property string|null $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereIdInternalAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereTypeHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryInternalAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HistoryInternalAccount extends Model
{
    protected $table = 'history_internal_accounts';

    protected $fillable = [
        'id_internal_account',
        'id_user',
        'id_task',
        'type_history',
        'description',
        'amount',
    ];
}
