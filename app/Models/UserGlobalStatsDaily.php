<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGlobalStatsDaily extends Model
{
    protected $table = 'user_global_stats_daily';

    protected $fillable = [
        'date',
        'total_users',
        'new_users',
        'active_users',
        'users_with_orders',
        'total_orders',
        'successful_orders',
        'failed_orders',
        'canceled_orders',
        'total_volume_usd',
        'total_profit_usd',
        'avg_orders_per_active_user',
        'avg_volume_per_active_user',
    ];

    protected $casts = [
        'date' => 'date',
        'total_volume_usd' => 'decimal:8',
        'total_profit_usd' => 'decimal:8',
        'avg_orders_per_active_user' => 'decimal:4',
        'avg_volume_per_active_user' => 'decimal:8',
    ];
}
