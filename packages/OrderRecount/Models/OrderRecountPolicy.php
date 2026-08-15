<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderRecountPolicy extends Model
{
    protected $table = 'order_recount_policies';

    protected $fillable = [
        'is_enabled',
        'priority',
        'scope_type',
        'scope_id',
        'trigger_types',
        'conditions',
        'actions',
        'stop_further',
        'title',
    ];

    protected $casts = [
        'is_enabled' => 'bool',
        'stop_further' => 'bool',
        'trigger_types' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
    ];
}
