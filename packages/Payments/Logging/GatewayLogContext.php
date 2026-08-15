<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Logging;

final class GatewayLogContext
{
    public function __construct(
        public readonly string $gatewayAlias,
        public readonly string $direction,
        public readonly string $operation,
        public readonly ?int $taskId = null,
        public readonly ?int $merchantId = null,
        public readonly ?int $paymentId = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $externalId = null,
        public readonly int $attempt = 1,
        public readonly ?string $idempotencyKey = null,
        public readonly array $meta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'gateway_alias'   => $this->gatewayAlias,
            'direction'       => $this->direction,
            'operation'       => $this->operation,
            'task_id'         => $this->taskId,
            'merchant_id'     => $this->merchantId,
            'payment_id'      => $this->paymentId,
            'transaction_id'  => $this->transactionId,
            'external_id'     => $this->externalId,
            'attempt'         => $this->attempt,
            'idempotency_key' => $this->idempotencyKey,
            'meta'            => $this->meta,
        ];
    }
}
