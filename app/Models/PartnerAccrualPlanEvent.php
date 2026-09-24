<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAccrualPlanEvent extends Model
{
    public $timestamps = false;

    protected $table = 'partner_accrual_plan_events';

    protected $fillable = [
        'plan_id',
        'event_type',
        'actor_admin_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'plan_id' => 'integer',
        'actor_admin_id' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PartnerAccrualPlan::class, 'plan_id');
    }
}
