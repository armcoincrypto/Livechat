<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_UNRESOLVED = 'unresolved';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_FAILED = 'failed';

    protected $table = 'provider_webhook_events';

    protected $fillable = [
        'provider',
        'event_id',
        'delivery_id',
        'event_type',
        'provider_payment_id',
        'task_id',
        'payload_hash',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
