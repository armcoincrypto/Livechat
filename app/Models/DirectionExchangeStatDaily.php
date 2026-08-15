<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectionExchangeStatDaily extends Model
{
    protected $table = 'direction_exchange_stats_daily';

    protected $fillable = [
        'stat_date',
        'direction_exchange_id',
        'total_orders',
        'completed_orders',
        'rejected_orders',
        'cancelled_orders',
        'processing_orders',
        'total_amount_from_usd',
        'total_amount_to_usd',
        'total_profit_usd',
        'unique_users',
        'new_users',
    ];

    protected $casts = [
        'stat_date'             => 'date',
        'total_orders'          => 'integer',
        'completed_orders'      => 'integer',
        'rejected_orders'       => 'integer',
        'cancelled_orders'      => 'integer',
        'processing_orders'     => 'integer',
        'total_amount_from_usd' => 'string',
        'total_amount_to_usd'   => 'string',
        'total_profit_usd'      => 'string',
        'unique_users'          => 'integer',
        'new_users'             => 'integer',
    ];

    public function directionExchange(): BelongsTo
    {
        return $this->belongsTo(DirectionExchange::class, 'direction_exchange_id');
    }
}
