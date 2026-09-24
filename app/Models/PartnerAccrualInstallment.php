<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Services\PartnerAccrual\PartnerAccrualCurrency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAccrualInstallment extends Model
{
    protected $table = 'partner_accrual_installments';

    protected $fillable = [
        'plan_id',
        'sequence',
        'scheduled_for',
        'amount',
        'status',
        'idempotency_key',
        'balance_log_id',
        'executed_at',
        'attempt_count',
        'last_error',
    ];

    protected $casts = [
        'status' => PartnerAccrualInstallmentStatus::class,
        'scheduled_for' => 'date',
        'executed_at' => 'datetime',
        'sequence' => 'integer',
        'plan_id' => 'integer',
        'balance_log_id' => 'integer',
        'attempt_count' => 'integer',
        'amount' => 'string',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PartnerAccrualPlan::class, 'plan_id');
    }

    public function ensureIdempotencyKey(): string
    {
        if ($this->idempotency_key) {
            return (string) $this->idempotency_key;
        }

        $key = PartnerAccrualCurrency::idempotencyKey((int) $this->plan_id, (int) $this->id);
        $this->idempotency_key = $key;

        return $key;
    }
}
