<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralAuditLog extends Model
{
    protected $table = 'referral_audit_logs';

    protected $fillable = [
        'event',
        'level',
        'partner_user_id',
        'client_user_id',
        'referral_link_id',
        'referral_program_id',
        'task_id',
        'message',
        'meta',
        'trace_id',
    ];

    protected $casts = [
        'partner_user_id' => 'integer',
        'client_user_id' => 'integer',
        'referral_link_id' => 'integer',
        'referral_program_id' => 'integer',
        'task_id' => 'integer',
        'meta' => 'array',
    ];
}
