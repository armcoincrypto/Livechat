<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_old_operator
 * @property int $id_operator
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $id_task
 * @property-read \App\Models\User|null $new_operator
 * @property-read \App\Models\User|null $old_operator
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereIdOldOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereIdOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksOperatorLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TasksOperatorLog extends Model
{
    protected $table = 'tasks_operators_logs';

    protected $fillable = [
        'id_old_operator',
        'id_operator',
        'message',
        'id_task',
    ];

    public function old_operator()
    {
        return $this->hasOne(User::class, 'id', 'id_old_operator');
    }

    public function new_operator()
    {
        return $this->hasOne(User::class, 'id', 'id_operator');
    }
}
