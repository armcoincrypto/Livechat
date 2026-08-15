<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderRecountDailyStat extends Model
{
    protected $table = 'order_recount_daily_stats';
    public $timestamps = false;

    protected $fillable = [
        'day','trigger','decision','reason_code','scope_type','scope_id',
        'events_count',
        'perf_ms_sum','calc_ms_sum','recount_ms_sum',
        'perf_ms_count','calc_ms_count','recount_ms_count',
        'updated_at',
    ];

    protected $casts = [
        'day' => 'date',
        'scope_id' => 'int',
        'events_count' => 'int',
        'perf_ms_sum' => 'int',
        'calc_ms_sum' => 'int',
        'recount_ms_sum' => 'int',
        'perf_ms_count' => 'int',
        'calc_ms_count' => 'int',
        'recount_ms_count' => 'int',
        'updated_at' => 'datetime',
    ];
}
