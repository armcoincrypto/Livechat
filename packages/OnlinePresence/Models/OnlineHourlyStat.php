<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Models;

use Illuminate\Database\Eloquent\Model;

final class OnlineHourlyStat extends Model
{
    protected $table = 'online_hourly_stats';

    protected $fillable = [
        'hour',
        'auth_hits',
        'guest_hits',
        'concurrent_last',
        'concurrent_max',
    ];

    protected $casts = [
        'hour' => 'datetime',
    ];
}
