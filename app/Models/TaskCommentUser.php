<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_manager
 * @property int $id_task
 * @property string|null $message
 * @property string|null $class_style
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereClassStyle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCommentUser whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskCommentUser extends Model
{
    protected $table = 'tasks_comments_users';

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
