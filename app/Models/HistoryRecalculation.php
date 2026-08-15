<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 *
 * @property int $id
 * @property int $id_task
 * @property float $amount
 * @property float $old_amount
 * @property int $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $course
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Task|null $tasks
 * @property-read \App\Models\TaskInfo|null $tasks_info
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation query()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereCourse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereOldAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryRecalculation withoutTrashed()
 * @mixin \Eloquent
 */
class HistoryRecalculation extends Model
{
    use SoftDeletes;

    protected $table = 'history_recalculation';

    protected $fillable = [
        'id_task',
        'amount',
        'old_amount',
        'type',
        'course',
        'course_value',
    ];

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }

    public function tasks_info()
    {
        return $this->hasOne(TaskInfo::class, 'id_task', 'id_task');
    }
}
