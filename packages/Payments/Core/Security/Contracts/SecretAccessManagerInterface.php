<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Security\Contracts;

interface SecretAccessManagerInterface
{
    public function canView(string $scope): bool;

    /**
     * Выдать доступ к секретам scope на ttl секунд.
     */
    public function grant(string $scope, int $ttlSeconds): void;

    public function revoke(string $scope): void;

    /**
     * Получить placeholder для скрытого поля.
     */
    public function placeholder(string $value): string;
}
