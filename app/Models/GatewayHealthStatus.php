<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GatewayHealthStatus extends Model
{
    protected $table = 'gateway_health_statuses';

    protected $fillable = [
        'type',
        'entity_id',
        'gateway_alias',
        'status',
        'http_status',
        'latency_ms',
        'message',
        'fail_streak',
        'last_ok_at',
        'checked_at',
    ];

    protected $casts = [
        'last_ok_at' => 'datetime',
        'checked_at' => 'datetime',
    ];
}
