<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Traits;

use iEXPackages\DynamicConfig\Models\DynamicConfigLock;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Str;

/**
 * Trait DynamicConfigSchemaAndLockTrait
 *
 * Всё, что связано с:
 *  - применением схемы (inline + global);
 *  - защищёнными ключами (protected_keys);
 *  - блокировкой ключей по схеме (locked = true);
 *  - временными lock’ами (dynamic_config_locks).
 */
trait DynamicConfigSchemaAndLockTrait
{
    /**
     * Кеш lock’ов для scope в рамках одного запроса.
     *
     * @var array<string, array<string, DynamicConfigLock|false>>
     */
    private array $locksCache = [];

    /**
     * Нормализация/валидация значения:
     *
     * 1) если есть инлайновая схема и в ней есть ключ — используем её;
     * 2) иначе, если есть глобальная схема (ConfigSchemaRegistry) — используем её;
     * 3) иначе возвращаем значение как есть.
     */
    private function normalizeWithSchema(
        string $key,
        mixed $value,
        ?string $schemaProfile,
        ?array $schemaInline,
        ?Scope $scope = null
    ): mixed {
        // 0. scope по умолчанию — текущий, если не передан
        $scope ??= $this->scopeResolver->currentScope();

        // 1. если есть inline-схема — она в приоритете
        if (is_array($schemaInline) && array_key_exists($key, $schemaInline)) {
            $definition = $schemaInline[$key];

            $type     = $definition['type'] ?? 'mixed';
            $nullable = (bool)($definition['nullable'] ?? false);

            if ($value === null) {
                if ($nullable) {
                    return null;
                }

                if (array_key_exists('default', $definition)) {
                    return $definition['default'];
                }

                throw new \InvalidArgumentException(
                    sprintf('Значение "%s" не может быть null (inline schema).', $key)
                );
            }

            return $this->normalizeInlineValue($key, $value, $definition);
        }

        // 2. Выбираем профиль схемы:
        //    - если явно передан $schemaProfile — используем его;
        //    - иначе — используем scope_type (global, plugins, language, ...)
        $profile = $schemaProfile ?? $scope->type ?? 'global';

        // 3. Глобальная схема (default + профиль)
        if ($this->schema !== null) {
            return $this->schema->normalizeAndValidate($key, $value, $profile);
        }

        // 4. Если схемы нет — возвращаем как есть
        return $value;
    }

    /**
     * Минимальный валидатор для инлайновой схемы.
     *
     * Поддерживает:
     *  - type: string|int|float|bool|array
     *  - default
     *  - min/max для чисел
     */
    private function normalizeInlineValue(string $key, mixed $value, array $definition): mixed
    {
        $type = $definition['type'] ?? 'mixed';

        $normalized = match ($type) {
            'string' => $this->castInlineString($value, $key),
            'int'    => $this->castInlineInt($value, $key),
            'float'  => $this->castInlineFloat($value, $key),
            'bool'   => $this->castInlineBool($value),
            'array'  => $this->castInlineArray($value, $key),
            default  => $value,
        };

        if (is_numeric($normalized)) {
            if (array_key_exists('min', $definition) && $normalized < $definition['min']) {
                throw new \InvalidArgumentException(
                    sprintf('Inline schema: "%s" меньше min (%s).', $key, $definition['min'])
                );
            }

            if (array_key_exists('max', $definition) && $normalized > $definition['max']) {
                throw new \InvalidArgumentException(
                    sprintf('Inline schema: "%s" больше max (%s).', $key, $definition['max'])
                );
            }
        }

        return $normalized;
    }

    private function castInlineString(mixed $value, string $key): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(
            sprintf('Inline schema: "%s" должно быть строкой.', $key)
        );
    }

    private function castInlineInt(mixed $value, string $key): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(
            sprintf('Inline schema: "%s" должно быть целым числом.', $key)
        );
    }

    private function castInlineFloat(mixed $value, string $key): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new \InvalidArgumentException(
            sprintf('Inline schema: "%s" должно быть числом.', $key)
        );
    }

    private function castInlineBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function castInlineArray(mixed $value, string $key): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        throw new \InvalidArgumentException(
            sprintf('Inline schema: "%s" должно быть массивом или JSON-строкой.', $key)
        );
    }

    /**
     * Проверка, защищён ли ключ от изменения по конфигу (protected_keys).
     *
     * Шаблоны в config('dynamic_config.protected_keys'):
     *  - "env.*"
     *  - "env.mail_*"
     *  - "license.key"
     *  - "security.password"
     */
    private function isProtectedKey(string $key): bool
    {
        $patterns = config('dynamic_config.protected_keys', []);

        foreach ($patterns as $pattern) {
            // Позволяем полную силу масок: "env.*", "license.*", "secret_*"
            if (Str::is($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка, заблокирован ли ключ в схеме (locked = true).
     */
    private function isLockedBySchema(string $key, ?string $schemaProfile = null): bool
    {
        if ($this->schema === null) {
            return false;
        }

        $definition = $this->schema->findDefinition($key, $schemaProfile);

        return !empty($definition['locked'] ?? false);
    }

    /**
     * Проверка временного lock’а из таблицы dynamic_config_locks.
     *
     * Результаты кешируются в пределах запроса (для всех операций записи),
     * чтобы не выполнять повторные SELECT для одного и того же scope/ключа.
     */
    private function isTemporarilyLocked(Scope $scope, string $key): bool
    {
        if (!config('dynamic_config.locks_enabled', true)) {
            return false;
        }

        $scopeKey = $scope->cacheKey();

        // Если уже проверяли этот ключ — возвращаем кешированный результат
        if (isset($this->locksCache[$scopeKey][$key])) {
            $cached = $this->locksCache[$scopeKey][$key];

            return $cached instanceof DynamicConfigLock;
        }

        /** @var DynamicConfigLock|null $lock */
        $lock = DynamicConfigLock::query()
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->where('key', $key)
            ->first();

        if (!$lock) {
            $this->locksCache[$scopeKey][$key] = false;
            return false;
        }

        if ($lock->locked_permanent) {
            $this->locksCache[$scopeKey][$key] = $lock;
            return true;
        }

        if ($lock->locked_until !== null && now()->lt($lock->locked_until)) {
            $this->locksCache[$scopeKey][$key] = $lock;
            return true;
        }

        // lock устарел — удаляем
        $lock->delete();
        $this->locksCache[$scopeKey][$key] = false;

        return false;
    }
}
