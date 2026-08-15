<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use iEXPackages\Payments\Core\Contracts\HealthResponseInterface;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Унифицированный ответ health-check.
 *
 * Это service-операция, поэтому:
 * - не про платежи
 * - не про редиректы
 * - не про реквизиты
 *
 * Но она обязана соответствовать ResponseInterface, чтобы Gateway->run('health') работал одинаково.
 */
final class HealthResponse implements ResponseInterface, HealthResponseInterface
{
    /**
     * @param string $status ok|degraded|fail
     */
    public function __construct(
        private readonly string $status,
        private readonly ?string $message = null,
        private readonly ?int $httpStatus = null,
        private readonly ?int $latencyMs = null,
        private readonly array $data = [],
        private readonly array $query = [],
        private readonly mixed $raw = null,
    ) {}

    // ---------------------------------------------------------------------
    // HealthResponseInterface
    // ---------------------------------------------------------------------

    public function getHealthStatus(): string
    {
        return $this->status; // ok | degraded | fail
    }

    public function getHealthMessage(): ?string
    {
        return $this->message;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }

    // ---------------------------------------------------------------------
    // ResponseInterface
    // ---------------------------------------------------------------------

    public function isSuccessful(): bool
    {
        return $this->status === 'ok';
    }

    public function isPending(): bool
    {
        return false;
    }

    public function isCancelled(): bool
    {
        return false;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccessful();
    }

    public function getRaw(): mixed
    {
        return $this->raw;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function toArray(): array
    {
        return [
            'ok'         => $this->isSuccessful(),
            'status'     => $this->getHealthStatus(),
            'message'    => $this->getHealthMessage(),
            'httpStatus' => $this->getHttpStatus(),
            'latencyMs'  => $this->getLatencyMs(),
            'data'       => $this->getData(),
            'query'      => $this->getQuery(),
        ];
    }
}
