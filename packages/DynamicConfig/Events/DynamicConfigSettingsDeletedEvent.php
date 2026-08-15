<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Events;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Событие вызывается при удалении одного или нескольких ключей через delete().
 */
final class DynamicConfigSettingsDeletedEvent
{
    /**
     * @param string[] $keys
     * @param array<string, mixed> $oldValues
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly array $keys,
        public readonly array $oldValues,
    ) {
    }
}
