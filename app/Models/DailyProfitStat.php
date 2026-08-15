<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyProfitStat extends Model
{
    protected $table = 'daily_profit_stats';

    protected $fillable = [
        'stat_date',
        'direction_id',
        'total_orders',
        'total_profit_usd',
        'avg_profit_usd',
        'min_profit_usd',
        'max_profit_usd',
        'direction_name',
        'currency_from',
        'currency_to'
    ];

    protected $casts = [
        'stat_date'        => 'date',
        'total_orders'     => 'int',
        'total_profit_usd' => 'string',
        'avg_profit_usd'   => 'string',
        'min_profit_usd'   => 'string',
        'max_profit_usd'   => 'string',
        'direction_name'   => 'string',
        'currency_from'    => 'string',
        'currency_to'      => 'string',
    ];

    public function direction()
    {
        return $this->belongsTo(\App\Models\DirectionExchange::class, 'direction_id');
    }
}
