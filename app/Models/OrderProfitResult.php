<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProfitResult extends Model
{
    protected $table = 'order_profit_results';

    protected $fillable = [
        'task_id',
        'profit_amount',
        'profit_currency_code',
        'profit_amount_usd',
        'base_currency_code',
        'profit_percent_effective',
        'rates_snapshot',
        'breakdown_json',
        'calculated_at',
    ];

    protected $casts = [
        'profit_amount'            => 'string',
        'profit_amount_usd'        => 'string',
        'profit_percent_effective' => 'string',
        'rates_snapshot'           => 'array',
        'breakdown_json'           => 'array',
        'calculated_at'            => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(\App\Models\Task::class, 'task_id');
    }

    public function direction()
    {
        return $this->hasOneThrough(
            \App\Models\DirectionExchange::class,
            \App\Models\Task::class,
            'id',                    // Task.id
            'id',                    // DirectionExchange.id
            'task_id',               // OrderProfitResult.task_id
            'id_direction_exchange'  // Task.direction_exchange_id
        );
    }
}
