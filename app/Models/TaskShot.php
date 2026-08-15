<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int|null $id_task
 * @property string|null $account
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot whereAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskShot whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskShot extends Model
{
    protected $table = 'tasks_shots';

    protected $fillable = [
        'id_task',
        'account',
    ];
}
