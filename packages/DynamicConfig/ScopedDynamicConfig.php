<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Класс ScopedDynamicConfig
 *
 * Обёртка над DynamicConfigManager для конкретного scope.
 *
 * Позволяет работать с настройками, не передавая scope каждый раз:
 *
 *  $userScope = new ScopedDynamicConfig($manager, Scope::fromString('user', $userId));
 *  $theme     = $userScope->get('ui.theme');
 *  $userScope->set('ui.theme', 'dark');
 */
final class ScopedDynamicConfig
{
    public function __construct(
        private readonly DynamicConfigManager $manager,
        private readonly Scope $scope
    ) {
    }

    /**
     * Получить scope, к которому привязан данный объект.
     */
    public function scope(): Scope
    {
        return $this->scope;
    }

    /**
     * Получить значение настройки.
     *
     * @param string      $key
     * @param mixed|null  $default
     * @param string|null $locale
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        return $this->manager->get($key, $default, $this->scope, $locale);
    }

    /**
     * Установить значение настройки.
     *
     * @param string      $key
     * @param mixed       $value
     * @param string|null $schemaProfile
     * @param array|null  $schemaInline
     */
    public function set(
        string $key,
        mixed $value,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $this->manager->set($key, $value, $this->scope, $schemaProfile, $schemaInline);
    }

    /**
     * Типизированное чтение значения: int|float|bool|string|json.
     */
    public function getAs(
        string $key,
        string $type,
        mixed $default = null,
        ?string $locale = null
    ): mixed {
        return $this->manager->getAs($key, $type, $default, $this->scope, $locale);
    }

    /**
     * Установить значение с указанием режима (replace/merge/skip-existing/force).
     */
    public function setWithMode(
        string $key,
        mixed $value,
        string $mode = 'replace',
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $this->manager->setWithMode($key, $value, $mode, $this->scope, $schemaProfile, $schemaInline);
    }

    /**
     * Удалить один или несколько ключей (soft-delete).
     *
     * @param string|string[] $keys
     */
    public function delete(string|array $keys): void
    {
        $this->manager->delete($keys, $this->scope);
    }

    /**
     * Заблокировать ключ до определённой даты или навсегда.
     */
    public function lockKey(
        string $key,
        \DateTimeInterface|string|null $until = null,
        bool $permanent = false,
        ?string $reason = null
    ): void {
        $this->manager->lockKey($key, $this->scope, $until, $permanent, $reason);
    }

    /**
     * Безопасное чтение: возвращает маску вместо значения, если ключ существует.
     *
     * @return string|null Маска или null, если ключ не найден.
     */
    public function safe(string $key, string $mask = '***'): ?string
    {
        return $this->manager->safe($key, $mask, $this->scope);
    }

    /**
     * Дополнительные удобные методы:
     */

    public function bool(string $key, bool $default = false): bool
    {
        return $this->manager->bool($key, $default, $this->scope);
    }

    public function int(string $key, int $default = 0): int
    {
        return $this->manager->int($key, $default, $this->scope);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return $this->manager->float($key, $default, $this->scope);
    }

    public function string(string $key, string $default = '', ?string $locale = null): string
    {
        return $this->manager->string($key, $default, $this->scope, $locale);
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        return $this->manager->array($key, $default, $this->scope);
    }
}
