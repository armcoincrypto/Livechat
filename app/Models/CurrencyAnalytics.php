<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Currency;

class CurrencyAnalytics extends Model
{
    protected $table = 'currencies_analytics';

    protected $fillable = [
        'id_currency',
        'in_amount',
        'out_amount',
        'in_amount_usd',
        'out_amount_usd',
        'in_count_exchange',
        'out_count_exchange',
        'in_order_count',
        'out_order_count',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'id_currency');
    }
}
