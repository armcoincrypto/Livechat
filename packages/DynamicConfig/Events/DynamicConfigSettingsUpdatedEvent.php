<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Events;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Событие вызывается при массовом обновлении настроек через update().
 *
 * $changes имеет формат:
 *  [
 *      'some.key' => ['old' => ..., 'new' => ...],
 *      ...
 *  ]
 */
final class DynamicConfigSettingsUpdatedEvent
{
    /**
     * @param array<string, array{old:mixed,new:mixed}> $changes
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly array $changes,
    ) {
    }
}
