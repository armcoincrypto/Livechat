<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_task
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task|null $tasks
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskOperator whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskOperator extends Model
{
    protected $table = 'tasks_operators';

    protected $fillable = [
        'id_task',
        'id_user',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
