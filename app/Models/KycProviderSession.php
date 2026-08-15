<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProviderSession extends Model
{
    protected $table = 'kyc_provider_sessions';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_session_id',
        'provider_workflow_id',
        'provider_reference',
        'normalized_status',
        'provider_status',
        'verification_url',
        'last_event_id',
        'started_at',
        'completed_at',
        'last_synced_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->normalized_status, [
            'not_started',
            'pending',
            'manual_review',
        ], true);
    }

    public function isApproved(): bool
    {
        return $this->normalized_status === 'approved';
    }
}
