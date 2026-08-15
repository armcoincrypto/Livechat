<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use iEXPackages\GeoIp\Contracts\DynamicConfigReaderInterface;
use iEXPackages\GeoIp\Contracts\ToggleSourceInterface;

final class DynamicConfigToggleSource implements ToggleSourceInterface
{
    public function __construct(
        private readonly DynamicConfigReaderInterface $reader,
        private readonly string $prefix,
        private readonly bool $enabled,
        /** @var string[] */
        private readonly array $allowedKeys = [],
    ) {}

    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }
        if ($this->allowedKeys !== [] && !in_array($key, $this->allowedKeys, true)) {
            return null;
        }

        return $this->reader->get($this->prefix.$key);
    }
}
