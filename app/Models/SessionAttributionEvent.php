<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privacy-safe attribution lifecycle event (C.3E).
 * Minimal columns only — no UTM/PII duplication.
 */
class SessionAttributionEvent extends Model
{
    public $timestamps = false;

    protected $table = 'session_attribution_events';

    protected $fillable = [
        'session_attribution_id',
        'task_id',
        'event_type',
        'status_code',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'session_attribution_id' => 'integer',
        'task_id' => 'integer',
        'status_code' => 'integer',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function sessionAttribution(): BelongsTo
    {
        return $this->belongsTo(SessionAttribution::class, 'session_attribution_id');
    }
}
