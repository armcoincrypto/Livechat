<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycLog extends Model
{
    protected $table = 'kyc_events';

    protected $fillable = [
        'provider',
        'event',
        'status',
        'user_id',
        'subject_external_id',
        'stage',
        'outcome',
        'outcome_reason',
        'response_data',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'response_data' => 'array',
        'meta'          => 'array',
        'occurred_at'   => 'datetime',
    ];
}
