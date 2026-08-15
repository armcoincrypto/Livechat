<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property int $id_manager
 * @property string|null $text
 * @property string|null $file
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskFile whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskFile extends Model
{
    protected $table = 'tasks_files';

    protected $fillable = [
        'id_manager',
        'id_task',
        'text',
        'file',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }
}
