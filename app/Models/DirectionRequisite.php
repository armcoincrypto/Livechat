<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $account_number
 * @property int $view
 * @property float $limit_day
 * @property float $limit_month
 * @property int $limit_views
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite activeWallet()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereLimitDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereLimitMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereLimitViews($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionRequisite whereView($value)
 * @mixin \Eloquent
 */
class DirectionRequisite extends Model
{
    use Filterable;

    protected $table = 'direction_requisites';

    protected $fillable = [
        'name',
        'account_number',
        'view',
        'limit_day',
        'limit_month',
        'limit_views',
        'status',
    ];

    /**
     * Запрос на включенение только активных счетов
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveWallet($query)
    {
        return $query->where('status', '=', 0);
    }

    public function direction_exchange(): MorphToMany
    {
        return $this->morphedByMany(DirectionExchange::class, 'model', 'directions_has_requisites', 'direction_requisite_id');
    }
}
