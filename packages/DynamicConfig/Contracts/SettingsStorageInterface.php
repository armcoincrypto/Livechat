<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Contracts;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Контракт хранилища DynamicConfig.
 */
interface SettingsStorageInterface
{
    /**
     * Загрузить все настройки для конкретного scope.
     *
     * @return array Ассоциативный массив настроек (вложенные структуры разрешены).
     */
    public function load(Scope $scope): array;

    /**
     * Сохранить все настройки для конкретного scope (полная картина для уровня).
     */
    public function save(Scope $scope, array $settings): void;

    /**
     * Полностью очистить настройки конкретного scope.
     */
    public function clear(Scope $scope): void;
}
