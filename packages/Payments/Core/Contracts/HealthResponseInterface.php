<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

interface HealthResponseInterface
{
    public function getHealthStatus(): string;   // ok|degraded|fail
    public function getHealthMessage(): ?string;
    public function getHttpStatus(): ?int;
    public function getLatencyMs(): ?int;
}
