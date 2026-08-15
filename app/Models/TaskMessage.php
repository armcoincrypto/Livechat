<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 *
 *
 * @property int $id
 * @property int $id_task
 * @property int $user_id
 * @property string|null $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $type_user
 * @property int $is_view
 * @property int $id_manager
 * @property int $public_id
 * @property-read \App\Models\User|null $manager
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereIsView($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage wherePublicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereTypeUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMessage whereUserId($value)
 * @mixin \Eloquent
 */
class TaskMessage extends Model
{
    use Filterable;

    protected $table = 'tasks_messages';

    protected $fillable = [
        'id_task',
        'user_id',
        'id_manager',
        'message',
        'type_user',
        'is_view',
        'is_read',
        'file_path',
        'ai_meta'
    ];

    protected $casts = [
        'ai_meta' => 'array',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\TaskMessageFilter::class);
    }


    public function task(): HasOne
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function manager(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(TaskMessage::class, 'id_task', 'id_task')->orderBy('created_at', 'desc');
    }

    public function unread()
    {
        return $this->hasMany(TaskMessage::class, 'id_task', 'id_task')->where('is_read', 0);
    }
}
