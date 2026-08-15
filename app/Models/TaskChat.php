<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_task
 * @property string|null $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $id_client
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereIdClient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskChat whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskChat extends Model
{
    protected $table = 'tasks_chat';

    protected $fillable = [
        'id_task',
        'id_client',
        'id_user',
        'message',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
}
