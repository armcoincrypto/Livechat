<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 *
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $file_one
 * @property string|null $file_two
 * @property string|null $fio_user
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $hash_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereFileOne($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereFileTwo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereFioUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereHashId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserVerification whereUserId($value)
 * @mixin \Eloquent
 */
class UserVerification extends Model
{
    use Filterable, Notifiable;

    protected $table = 'user_verification';

    protected $fillable = [
        'user_id',
        'file_one',
        'file_two',
        'file_one_preview',
        'file_two_preview',
        'fio_user',
        'status',
        'ip_address',
        'user_agent',
        'hash_id',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
