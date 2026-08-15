<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Events;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Событие вызывается при установке/обновлении конкретного ключа через set().
 */
final class DynamicConfigSettingSetEvent
{
    public function __construct(
        public readonly Scope $scope,
        public readonly string $key,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
    ) {
    }
}
