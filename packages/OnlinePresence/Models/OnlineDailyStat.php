<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Models;

use Illuminate\Database\Eloquent\Model;

final class OnlineDailyStat extends Model
{
    protected $table = 'online_daily_stats';

    protected $fillable = [
        'day',
        'auth_unique',
        'guest_unique',
        'auth_hits',
        'guest_hits',
        'peak_concurrent',
    ];

    protected $casts = [
        'day' => 'date:Y-m-d',
    ];
}
