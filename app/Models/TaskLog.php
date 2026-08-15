<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property int $id_user
 * @property string|null $text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskLog extends Model
{
    protected $table = 'task_log';

    protected $fillable = [
        'id_task', 'id_user', 'text',
    ];
}
