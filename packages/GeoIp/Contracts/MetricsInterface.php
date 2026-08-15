<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

/**
 * Простейший контракт метрик.
 */
interface MetricsInterface
{
    public function inc(string $name, array $tags = [], int $value = 1): void;
    public function timing(string $name, float $ms, array $tags = []): void;
}
