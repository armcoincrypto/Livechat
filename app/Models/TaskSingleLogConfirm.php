<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property int $needed_confirm
 * @property int $received_confirm
 * @property string|null $transaction_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereNeededConfirm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereReceivedConfirm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskSingleLogConfirm whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskSingleLogConfirm extends Model
{
    protected $table = 'task_single_log_confirm';

    protected $fillable = [
        'id_task',
        'needed_confirm',
        'received_confirm',
        'transaction_id',
    ];
}
