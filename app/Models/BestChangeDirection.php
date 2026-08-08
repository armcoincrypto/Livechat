<?php

namespace App\Models;

use App\Models\Filters\BestchangeDirectionFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;


class BestChangeDirection extends Model
{
    use Filterable;


    protected $table = 'bestchange_directions';

    protected $fillable = [
        'name',
        'id_direction_exchange',
        'id_currency_in',
        'id_currency_out',
        'is_favorite',
        'position_num',
        'min_reserve',
        'rate_value_without_step',
        'max_reserve',
        'step',
        'reset_course',
        'standard_course',
        'min_sum',
        'max_sum',
        'minmax_sum_fee',
        'min_sum_new_default_parser',
        'min_sum_new_default_parser_fee',
        'is_parser_formula',
        'min_sum_new_formula_parser',
        'min_sum_new_formula_parser_fee',
        'whitelist_ids',
        'blacklist_ids',
        'city_id',
        'status',
        'is_error_parser',
        'rate_value',
        'source_name',
        'exchange_in',
        'exchange_out',
        'formula_value',
        'code',
        'rate_mode',
        'top_n',
        'explain_payload'
    ];

    protected $casts = [
        'top_n' => 'int',
        'status' => 'int',
        'city_id' => 'int',
        'is_favorite' => 'bool',
        'is_error_parser' => 'int',
        'explain_payload' => 'array'
    ];

    /**
     * Фильтры модели
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(BestchangeDirectionFilter::class);
    }

    public function direction_exchange(): HasOne
    {
        return $this->hasOne(DirectionExchange::class,'id','id_direction_exchange');
    }

    public function new_default_parser(): HasOne
    {
        return $this->hasOne(ParserExchange::class, 'id','min_sum_new_default_parser');
    }

    public function new_formula_parser()
    {
        return $this->hasOne(ParserFormulaRates::class,'id','min_sum_new_formula_parser');
    }
}
