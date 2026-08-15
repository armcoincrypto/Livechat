<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use BadMethodCallException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use iEXPackages\Payments\Core\Config\GatewayConfig;

final class InputsAccessor
{
    public function __construct(
        private readonly Collection   $merchantConfig,
        private readonly GatewayConfig $definition,
        private readonly string        $group,      // 'merchant', 'pay', ...
    ) {}

    /**
     * Все ключи основных полей (fields) для группы.
     */
    public function keys(): array
    {
        return $this->definition->inputKeys($this->group);
    }

    public function isDefined(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Найти схему поля по key в inputs.{group}.fields.
     */
    public function fieldSchema(string $key): ?array
    {
        $fields = $this->definition->fields($this->group);

        foreach ($fields as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Получить type поля из schema (value_type).
     *
     * Возможные варианты по договорённости:
     *   - string (по умолчанию)
     *   - int
     *   - bool
     *   - decimal (строка с числом)
     */
    protected function valueType(string $key): string
    {
        $schema = $this->fieldSchema($key);

        return $schema['value_type'] ?? 'string';
    }

    /**
     * Низкоуровневый доступ без приведения типов (сырой конфиг).
     */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        return $this->merchantConfig->get($key, $default);
    }

    /**
     * Основной метод: берёт значение из конфига мерчанта по ключу и
     * приводит к типу, указанному в schema (value_type).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isDefined($key)) {
            throw new BadMethodCallException("Input key [{$key}] is not defined in inputs.{$this->group}.fields");
        }

        $valueType = $this->valueType($key);
        $raw       = $this->merchantConfig->get($key, $default);

        return $this->castValue($raw, $valueType, $default);
    }

    /**
     * Приведение значений к ожидаемому типу.
     *
     * Если значение не соответствует типу — либо возвращаем default,
     * либо (если хочешь жёстко) можно бросать исключение.
     */
    protected function castValue(mixed $value, string $valueType, mixed $default = null): mixed
    {
        if ($value === null) {
            return $default;
        }

        return match ($valueType) {
            'int' => $this->castInt($value, $default),
            'bool' => $this->castBool($value, $default),
            'decimal' => $this->castDecimal($value, $default),
            default => $this->castString($value, $default),
        };
    }

    protected function castString(mixed $value, mixed $default = null): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    protected function castInt(mixed $value, mixed $default = null): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    protected function castBool(mixed $value, mixed $default = null): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return $default;
    }

    protected function castDecimal(mixed $value, mixed $default = null): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            if (is_numeric($value)) {
                // Возвращаем строку, чтобы не терять точность
                return (string) $value;
            }
        }

        return $default;
    }

    public function has(string $key): bool
    {
        return $this->merchantConfig->has($key);
    }

    /**
     * Магический sugar:
     *
     *   getApiKey()           → key = 'api_key'
     *   getPrivateKey()       → key = 'private_key'
     *   getMaxTimeRegisterInNetwork() → key = 'max_time_register_in_network'
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!str_starts_with($name, 'get') || strlen($name) <= 3) {
            throw new BadMethodCallException("Method {$name} does not exist on " . static::class);
        }

        $fieldPart = substr($name, 3);  // ApiKey, MaxTimeRegisterInNetwork
        $key       = Str::snake($fieldPart); // api_key, max_time_register_in_network

        $default   = $arguments[0] ?? null;

        return $this->get($key, $default);
    }
}
