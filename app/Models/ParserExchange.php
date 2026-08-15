<?php

namespace App\Models;

use App\Models\Filters\ParserExchangeFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class ParserExchange extends Model
{
    use Filterable;

    protected $table = 'parser_exchange';

    protected $fillable = [
        'name',
        'id_group',
        'value',
        'summa',
        'type',
        'number_format',
        'id_type_price',
        'status',
        'code_in',
        'code_out',
        'value_default',
        'summa_default',
        'is_not_update',
        'code',
        'type_price',
    ];

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(ParserExchangeFilter::class);
    }

    public function group_parse_exchange()
    {
        return $this->belongsTo(GroupParserExchange::class, 'id_group', 'id');
    }

    public function duplicate_count()
    {
        return $this->hasMany(ParserExchange::class, 'name', 'name')->where('status', '=', 1)->count();
    }

    public function direction_exchange()
    {
        return $this->hasMany(DirectionExchange::class, 'id_crypto_parser', 'id');
    }

    public function ratesHistoryLogs()
    {
        return $this->hasMany(RatesHistoryLog::class, 'id_source')
            ->where('source', 'source');
    }
}
