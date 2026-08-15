<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Context;

/**
 * ResourceBag — динамическое хранилище ресурсов (объектов).
 * Сюда кладём DirectionExchange, User и любые другие объекты.
 */
final class ResourceBag
{
    /** @var array<class-string, object> */
    private array $items = [];

    public function set(object $resource): void
    {
        $this->items[$resource::class] = $resource;
    }

    /** @template T of object @param class-string<T> $class @return T */
    public function get(string $class): object
    {
        if (!isset($this->items[$class])) {
            throw new \RuntimeException("Validation resource not found: {$class}");
        }
        return $this->items[$class];
    }

    public function has(string $class): bool
    {
        return isset($this->items[$class]);
    }
}
