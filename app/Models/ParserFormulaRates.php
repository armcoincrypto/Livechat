<?php

namespace App\Models;

use App\Models\Filters\ParserFormulaRatesFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $exchange_in
 * @property string|null $exchange_out
 * @property string|null $formula
 * @property int $value
 * @property string|null $summa
 * @property int $status
 * @property int $number_format
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_coefficient
 * @property string|null $coefficient_formula
 * @property string|null $coefficient_name
 * @property string|null $title
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates query()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereCoefficientFormula($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereCoefficientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereExchangeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereExchangeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereFormula($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereIsCoefficient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereNumberFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereSumma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaRates whereValue($value)
 * @mixin \Eloquent
 */
class ParserFormulaRates extends Model
{
    use Filterable;

    protected $table = 'parser_formula_rates';

    protected $fillable = [
        'title',
        'name',
        'exchange_in',
        'exchange_out',
        'value',
        'summa',
        'status',
        'number_format',
        'is_error_update',
        'formula',
        'is_coefficient',
        'coefficient_name',
        'coefficient_formula',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(ParserFormulaRatesFilter::class);
    }

    public function direction_exchange(): HasMany
    {
        return $this->hasMany(DirectionExchange::class, 'id_parser_formula_rate', 'id');
    }

    public function ratesHistoryLogs()
    {
        return $this->hasMany(RatesHistoryLog::class, 'id_source')
            ->where('source', 'formula');
    }
}
