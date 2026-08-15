<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Privacy-safe anonymous attribution row (Batch 11).
 * Never stores wallets, emails, phones, IPs, or auth material.
 */
class SessionAttribution extends Model
{
    protected $table = 'session_attributions';

    protected $fillable = [
        'public_session_id',
        'first_utm_source',
        'first_utm_medium',
        'first_utm_campaign',
        'first_utm_term',
        'first_utm_content',
        'last_utm_source',
        'last_utm_medium',
        'last_utm_campaign',
        'last_utm_term',
        'last_utm_content',
        'first_referrer',
        'last_referrer',
        'first_landing_path',
        'last_landing_path',
        'locale',
        'device_class',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
