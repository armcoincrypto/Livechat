<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property int $id_manager
 * @property string|null $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $class_style
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereClassStyle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskComment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskComment extends Model
{
    protected $table = 'tasks_comments';

    protected $fillable = [
        'id_manager',
        'id_task',
        'message',
        'class_style',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }
}
