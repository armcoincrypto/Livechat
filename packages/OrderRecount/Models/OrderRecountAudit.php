<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderRecountAudit extends Model
{
    protected $table = 'order_recount_audit';
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'policy_id',
        'decision',
        'reason_code',
        'meta',
        'old_rate',
        'new_rate',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
