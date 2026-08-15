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
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedUser whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BannedUser extends Model
{
    protected $table = 'banned_user';

    protected $fillable = [
        'id_user',
        'id_task',
        'expired_at',
        'comment',
    ];
}
