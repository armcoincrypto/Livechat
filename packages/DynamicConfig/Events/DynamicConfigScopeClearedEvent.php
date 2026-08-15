<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Events;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Событие вызывается при полной очистке scope через clearScope().
 */
final class DynamicConfigScopeClearedEvent
{
    /**
     * @param array<string, mixed> $oldSettings
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly array $oldSettings,
    ) {
    }
}
