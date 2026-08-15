<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderRecountState extends Model
{
    protected $table = 'order_recount_states';
    protected $primaryKey = 'task_id';
    public $incrementing = false;

    protected $fillable = [
        'task_id',
        'last_recalculated_at_global',
        'last_rate_value_global',
        'last_recalculated_at_floating',
        'last_rate_value_floating',
        'status_last_global',
        'status_entered_at_global',
        'recount_in_status_count_global',
        'status_last_floating',
        'status_entered_at_floating',
        'recount_in_status_count_floating',
        'last_checked_at',
        'fail_streak',
        'locked_until',
    ];

    protected $casts = [
        'last_recalculated_at_global' => 'immutable_datetime',
        'last_recalculated_at_floating' => 'immutable_datetime',
        'status_entered_at_global' => 'immutable_datetime',
        'status_entered_at_floating' => 'immutable_datetime',
        'last_checked_at' => 'immutable_datetime',
        'locked_until' => 'immutable_datetime',
    ];
}
