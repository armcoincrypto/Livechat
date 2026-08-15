<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MerchantFlowEvent extends Model
{
    protected $table = 'merchant_flow_events';

    /**
     * Лучше whitelist, чем guarded=[].
     * Так ты точно знаешь, что может писаться в лог.
     */
    protected $fillable = [
        'task_id',
        'merchant_id',
        'gateway_alias',
        'flow',
        'stage',
        'event',
        'level',
        'ip',
        'user_agent',
        'message',
        'context',
        'checkout_id',
        'external_id',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(GatewayMerchant::class, 'merchant_id', 'id');
    }
}
