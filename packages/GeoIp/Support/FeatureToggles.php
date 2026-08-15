<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use iEXPackages\GeoIp\Contracts\ToggleSourceInterface;

final class FeatureToggles
{
    public function __construct(
        private readonly ToggleSourceInterface $source,
        private readonly array $defaults, // config('geoip.toggles')
    ) {}

    public function enabled(string $key, bool $fallback = false): bool
    {
        $v = $this->source->get($key);

        if (is_bool($v)) return $v;
        if (is_int($v)) return $v === 1;

        if (is_string($v)) {
            $vv = strtolower(trim($v));
            if (in_array($vv, ['1','true','yes','on'], true)) return true;
            if (in_array($vv, ['0','false','no','off'], true)) return false;
        }

        $cfg = $this->defaults[$key] ?? null;
        return is_bool($cfg) ? $cfg : $fallback;
    }
}
