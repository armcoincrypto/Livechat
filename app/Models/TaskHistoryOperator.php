<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $type
 * @property int $id_task
 * @property int $id_manager
 * @property int $id_status
 * @property string|null $in_amount
 * @property string|null $out_amount
 * @property string|null $course
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $id_from_manager
 * @property-read \App\Models\User|null $from_manager
 * @property-read \App\Models\Task|null $tasks
 * @property-read \App\Models\User|null $to_manager
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereCourse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereIdFromManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereIdStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereInAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereOutAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskHistoryOperator whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskHistoryOperator extends Model
{
    protected $table = 'tasks_history_operators';

    protected $fillable = [
        'type',
        'id_task',
        'id_manager',
        'id_from_manager',
        'id_status',
        'in_amount',
        'out_amount',
        'course',
    ];

    public function from_manager()
    {
        return $this->hasOne(User::class, 'id', 'id_from_manager');
    }

    public function to_manager()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
