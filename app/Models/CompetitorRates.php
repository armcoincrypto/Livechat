<?php

namespace App\Models;

use App\Models\Filters\CompetitorRatesFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string|null $name
 * @property int $id_competitor
 * @property int $value
 * @property string|null $summa
 * @property int $status
 * @property int $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_format
 * @property string|null $exchange_in
 * @property string|null $exchange_out
 * @property string|null $code
 * @property-read \App\Models\CompetitorLink|null $competitor_link
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereExchangeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereExchangeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereIdCompetitor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereNumberFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereSumma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorRates whereValue($value)
 * @mixin \Eloquent
 */
class CompetitorRates extends Model
{
    use Filterable;

    protected $table = 'competitor_rates';

    protected $fillable = [
        'name',
        'exchange_in',
        'exchange_out',
        'id_competitor',
        'value',
        'summa',
        'status',
        'number_format',
        'type',
        'code',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(CompetitorRatesFilter::class);
    }


    public function direction_exchange()
    {
        return $this->hasMany(DirectionExchange::class, 'id_competitor', 'id');
    }

    public function duplicate_count()
    {
        return $this->hasMany(CompetitorRates::class, 'name', 'name')->count();
    }

    public function competitor_link()
    {
        return $this->hasOne(CompetitorLink::class, 'id', 'id_competitor');
    }
}
