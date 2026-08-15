<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property string $exchange_course
 * @property string $market_course
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData query()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereExchangeCourse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereMarketCourse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TasksRatesData withoutTrashed()
 * @mixin \Eloquent
 */
class TasksRatesData extends Model
{
    use SoftDeletes;

    protected $table = 'tasks_rates_data';

    protected $fillable = [
        'id_task',
        'exchange_course',
        'market_course',
    ];
}
