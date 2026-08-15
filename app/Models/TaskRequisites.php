<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $type
 * @property string|null $account_number
 * @property int $id_task
 * @property int $id_direction_exchange
 * @property int $id_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereIdDirectionExchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRequisites whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskRequisites extends Model
{
    use Filterable;

    protected $table = 'tasks_requisites';

    protected $fillable = [
        'type',
        'account_number',
        'id_task',
        'id_direction_exchange',
        'id_currency',
    ];
}
