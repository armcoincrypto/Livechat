<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Context;

/**
 * DataBag — безопасный доступ к данным формы/запроса.
 * Не зависит от request(), поэтому легко тестировать.
 */
final class DataBag
{
    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** @template T @param T $default @return mixed|T */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getDot(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function getString(string $key): ?string
    {
        $v = $this->get($key);
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s !== '' ? $s : null;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }
}
