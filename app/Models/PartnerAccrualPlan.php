<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartnerAccrualStatus;
use App\Services\PartnerAccrual\PartnerAccrualCurrency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerAccrualPlan extends Model
{
    protected $table = 'partner_accrual_plans';

    protected $fillable = [
        'user_id',
        'currency_code',
        'currency_code_id',
        'total_amount',
        'credited_amount',
        'start_date',
        'end_date',
        'status',
        'created_by_admin_id',
        'paused_at',
        'cancelled_at',
        'completed_at',
        'error_code',
        'error_message',
        'metadata',
        'version',
        'schedule_seed',
        'min_installment',
        'max_installment',
        'variance_percent',
        'activated_at',
    ];

    protected $casts = [
        'status' => PartnerAccrualStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'activated_at' => 'datetime',
        'metadata' => 'array',
        'version' => 'integer',
        'currency_code_id' => 'integer',
        'created_by_admin_id' => 'integer',
        'user_id' => 'integer',
        'variance_percent' => 'integer',
        // Keep decimal amounts as strings — never cast to float.
        'total_amount' => 'string',
        'credited_amount' => 'string',
        'min_installment' => 'string',
        'max_installment' => 'string',
    ];

    public function installments(): HasMany
    {
        return $this->hasMany(PartnerAccrualInstallment::class, 'plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PartnerAccrualPlanEvent::class, 'plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assertUsdV1(): void
    {
        PartnerAccrualCurrency::assertSupported((string) $this->currency_code);
    }
}
