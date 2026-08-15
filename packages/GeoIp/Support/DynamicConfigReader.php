<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use iEXPackages\DynamicConfig\ValueObjects\Scope;
use iEXPackages\GeoIp\Contracts\DynamicConfigReaderInterface;

final class DynamicConfigReader implements DynamicConfigReaderInterface
{
    public function get(string $key): mixed
    {
        // если toggles должны быть глобальными:
        return iEXSetting($key, null, scope: Scope::fromString('global'));
    }
}
