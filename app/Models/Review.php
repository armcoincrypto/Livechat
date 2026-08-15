<?php

namespace App\Models;

use App\Models\Filters\ReviewFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $ip_address
 * @property int $status
 * @property string|null $text
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $user_agent
 * @property int $id_admin
 * @property int $rate_speed
 * @property int $id_task
 * @property int $id_direction_exchange
 * @property int $version
 * @property int $id_user
 * @property-read \App\Models\Task|null $tasks
 * @method static \Illuminate\Database\Eloquent\Builder|Review filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Review paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder|Review simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereIdAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereIdDirectionExchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereRateSpeed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Review whereVersion($value)
 * @mixin \Eloquent
 */
class Review extends Model
{
    use Filterable;

    protected $table = 'reviews';

    protected $fillable = [
        'id_admin',
        'ip_address',
        'user_agent',
        'status',
        'text',
        'name',
        'rate_speed',
        'id_task',
        'id_direction_exchange',
        'version',
        'id_user',
    ];

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(ReviewFilter::class);
    }

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
