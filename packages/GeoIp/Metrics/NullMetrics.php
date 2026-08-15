<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Metrics;

use iEXPackages\GeoIp\Contracts\MetricsInterface;

/**
 * Метрики по умолчанию: no-op.
 */
final class NullMetrics implements MetricsInterface
{
    public function inc(string $name, array $tags = [], int $value = 1): void {}
    public function timing(string $name, float $ms, array $tags = []): void {}
}
