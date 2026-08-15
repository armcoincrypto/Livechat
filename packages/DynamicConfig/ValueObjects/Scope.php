<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\ValueObjects;

/**
 * Value-object Scope
 *
 * Описывает уровень настроек (scope).
 *
 * Свойства:
 *  - type   — строковый тип scope (например, "global", "project", "client", "user").
 *  - id     — идентификатор сущности (например, ID проекта/клиента). Для global — null.
 *  - parent — родительский scope (или null, если это корень).
 *
 * Примеры:
 *  - Scope::global() → type="global", id=null, parent=null
 *  - Scope::fromString('project', 10) → type="project", id=10, parent=Scope::global()
 *  - Scope::fromString('client', 5) → parent=Scope('project', null) → parent=Scope('global', null)
 */
final class Scope
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $id = null,
        public readonly ?self $parent = null,
    ) {
    }

    /**
     * Глобальный scope (корень дерева scope).
     */
    public static function global(): self
    {
        return new self('global', null, null);
    }

    /**
     * Построить Scope по имени типа и ID, опираясь на конфиг dynamic_config.scopes.
     *
     * Пример:
     *  Scope::fromString('global', null)
     *  Scope::fromString('project', 10)
     *  Scope::fromString('client', 55)
     *
     * Конфиг dynamic_config.scopes:
     *  'scopes' => [
     *      'global'  => null,
     *      'project' => 'global',
     *      'client'  => 'project',
     *  ]
     */
    public static function fromString(string $type, ?int $id = null): self
    {
        $type = strtolower($type);

        $map = config('dynamic_config.scopes', ['global' => null]);

        if (!array_key_exists('global', $map)) {
            $map['global'] = null;
        }

        if ($type === 'global') {
            return self::global();
        }

        $parentType = $map[$type] ?? 'global';

        $parent = null;
        if ($parentType !== null) {
            $parent = self::fromString($parentType, null);
        }

        return new self($type, $id, $parent);
    }

    /**
     * Уникальный ключ для кеша по данному scope.
     *
     * Формат: "{type}:{id|null}", например:
     *  - "global:null"
     *  - "plugins:null"
     *  - "language:1"
     */
    public function cacheKey(): string
    {
        $id = $this->id === null ? 'null' : (string) $this->id;

        return sprintf('%s:%s', $this->type, $id);
    }

    /**
     * Цепочка scope’ов от корня до текущего уровня.
     *
     * Пример:
     *  global → project → client → user
     *
     * Вернёт:
     *  [Scope('global'), Scope('project'), Scope('client'), Scope('user')]
     *
     * @return self[]
     */
    public function chainToRoot(): array
    {
        $chain   = [];
        $current = $this;

        while ($current !== null) {
            $chain[] = $current;
            $current = $current->parent;
        }

        return array_reverse($chain);
    }
}
