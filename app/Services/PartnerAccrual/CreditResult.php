<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

/**
 * Result of creditInstallment / inspect.
 *
 * @phpstan-type CreditResultArray array{
 *   outcome: string,
 *   installment_id: int|null,
 *   plan_id: int|null,
 *   before: string|null,
 *   credit: string|null,
 *   after: string|null,
 *   audit_log_id: int|null,
 *   idempotency_key: string|null,
 *   plan_status: string|null,
 *   message: string|null
 * }
 */
final class CreditResult
{
    public const CREDITED = 'CREDITED';

    public const ALREADY_CREDITED = 'ALREADY_CREDITED';

    public const DRY_RUN_OK = 'DRY_RUN_OK';

    public function __construct(
        public readonly string $outcome,
        public readonly ?int $installmentId = null,
        public readonly ?int $planId = null,
        public readonly ?string $before = null,
        public readonly ?string $credit = null,
        public readonly ?string $after = null,
        public readonly ?int $auditLogId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $planStatus = null,
        public readonly ?string $message = null,
    ) {}

    public function isSuccess(): bool
    {
        return in_array($this->outcome, [self::CREDITED, self::ALREADY_CREDITED, self::DRY_RUN_OK], true);
    }

    public function balanceUnchanged(): bool
    {
        return $this->outcome === self::ALREADY_CREDITED;
    }

    /**
     * @return CreditResultArray
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'installment_id' => $this->installmentId,
            'plan_id' => $this->planId,
            'before' => $this->before,
            'credit' => $this->credit,
            'after' => $this->after,
            'audit_log_id' => $this->auditLogId,
            'idempotency_key' => $this->idempotencyKey,
            'plan_status' => $this->planStatus,
            'message' => $this->message,
        ];
    }
}
