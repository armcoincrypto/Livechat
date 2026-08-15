<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyAnalyticsDaily extends Model
{
    protected $table = 'currencies_analytics_daily';

    protected $fillable = [
        'date',
        'id_currency',
        'in_amount',
        'out_amount',
        'in_count',
        'out_count',
        'in_amount_usd',
        'out_amount_usd',
    ];

    protected $casts = [
        'date'           => 'date',
        'in_count'       => 'integer',
        'out_count'      => 'integer',
        'in_amount_usd'  => 'string',
        'out_amount_usd' => 'string',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'id_currency');
    }
}
