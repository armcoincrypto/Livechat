<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig;

use iEXPackages\DynamicConfig\Facades\DynamicConfig;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Базовый класс DynamicConfigModel
 *
 * Упрощает реализацию "моделей настроек" поверх DynamicConfig.
 *
 * Цели:
 *  - работать со Scope (уровнем настроек: global, license, user, plugins и т.п.);
 *  - строить ключи в формате "{prefix}.{field}";
 *  - предоставить удобные protected-методы getBool/getInt/getFloat/getString/getArray/getRaw;
 *  - дать единый update() / toArray() для массовой работы с полями.
 *
 * НЕ ДЕЛАЕТ:
 *  - автозагрузку публичных свойств;
 *  - автосохранение свойств;
 *  - ORM-механику.
 *
 * Пример использования:
 *
 *  final class DirectionConfig extends DynamicConfigModel
 *  {
 *      protected function prefix(): string
 *      {
 *          return 'direction_settings';
 *      }
 *
 *      protected function scopeType(): string
 *      {
 *          return 'global'; // или 'license','exchange','user','plugins'
 *      }
 *
 *      protected function fields(): array
 *      {
 *          return [
 *              'unpaid_auto_delete',
 *              'unpaid_order_status',
 *              'unpaid_time_day',
 *              'unpaid_time_hour',
 *              'unpaid_time_minute',
 *              'generate_min_price',
 *              'generate_max_price',
 *              'profit_calculation_type',
 *          ];
 *      }
 *
 *      public function unpaidAutoDelete(): bool
 *      {
 *          return $this->getBool('unpaid_auto_delete', false);
 *      }
 *
 *      // ...
 *  }
 */
abstract class DynamicConfigModel
{
    /**
     * Scope, в котором живут настройки данной модели.
     *
     * По умолчанию определяется через scopeType() и scopeId().
     */
    protected Scope $scope;

    /**
     * @param Scope|null $scope Scope, который будет использоваться для чтения/записи.
     *                          Если null, будет создан Scope::fromString(scopeType(), scopeId()).
     */
    public function __construct(?Scope $scope = null)
    {
        $this->scope = $scope ?? Scope::fromString(
            $this->scopeType(),
            $this->scopeId()
        );
    }

    /**
     * Префикс ключей в DynamicConfig. ОБЯЗАТЕЛЬНО в наследниках.
     *
     * Примеры:
     *  - 'direction_settings'
     *  - 'banners'
     *  - 'security'
     *  - 'bestchange'
     *
     * @return string
     */
    abstract protected function prefix(): string;

    /**
     * Тип scope (по умолчанию global).
     *
     * Можно переопределить:
     *  - 'license'
     *  - 'exchange'
     *  - 'user'
     *  - 'plugins'
     *  - и т.п.
     *
     * @return string
     */
    protected function scopeType(): string
    {
        return 'global';
    }

    /**
     * ID scope (по умолчанию null).
     *
     * Можно переопределить для биндинга к конкретной сущности:
     *  - license_id
     *  - user_id
     *  - project_id
     *
     * @return int|null
     */
    protected function scopeId(): ?int
    {
        return null;
    }

    /**
     * Построить полный ключ настройки по имени поля.
     *
     * field = 'timeout' → '{prefix}.timeout'
     *
     * @param string $field
     *
     * @return string
     */
    protected function key(string $field): string
    {
        $prefix = trim($this->prefix());

        // Если prefix пустой — ключ должен быть просто "field"
        if ($prefix === '') {
            return ltrim($field, '.');
        }

        // Нормализуем точки на всякий случай
        $prefix = rtrim($prefix, '.');
        $field  = ltrim($field, '.');

        return $prefix . '.' . $field;
    }

    // ---------------------------------------------------------------------
    // БАЗОВЫЕ GET-ОБЁРТКИ (protected, для использования в наследниках)
    // ---------------------------------------------------------------------

    /**
     * Прочитать bool-значение настройки.
     *
     * @param string $field
     * @param bool   $default
     *
     * @return bool
     */
    protected function getBool(string $field, bool $default = false): bool
    {
        return DynamicConfig::bool(
            $this->key($field),
            $default,
            $this->scope
        );
    }

    /**
     * Прочитать int-значение настройки.
     *
     * @param string $field
     * @param int    $default
     *
     * @return int
     */
    protected function getInt(string $field, int $default = 0): int
    {
        return DynamicConfig::int(
            $this->key($field),
            $default,
            $this->scope
        );
    }

    /**
     * Прочитать float-значение настройки.
     *
     * @param string $field
     * @param float  $default
     *
     * @return float
     */
    protected function getFloat(string $field, float $default = 0.0): float
    {
        return DynamicConfig::float(
            $this->key($field),
            $default,
            $this->scope
        );
    }

    /**
     * Прочитать string-значение настройки.
     *
     * @param string $field
     * @param string $default
     *
     * @return string
     */
    protected function getString(string $field, string $default = ''): string
    {
        return DynamicConfig::string(
            $this->key($field),
            $default,
            $this->scope
        );
    }

    /**
     * Прочитать массив (array) настройки.
     *
     * @param string       $field
     * @param array<mixed> $default
     *
     * @return array<mixed>
     */
    protected function getArray(string $field, array $default = []): array
    {
        return DynamicConfig::array(
            $this->key($field),
            $default,
            $this->scope
        );
    }

    /**
     * Прочитать "сырое" значение (без локализации и приведения типов).
     *
     * @param string $field
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function getRaw(string $field, mixed $default = null): mixed
    {
        return DynamicConfig::getRaw(
            $this->key($field),
            $default,
            $this->scope
        );
    }

    // ---------------------------------------------------------------------
    // БАЗОВАЯ update/toArray-ЛОГИКА (для наследников)
    // ---------------------------------------------------------------------

    /**
     * Список полей, которые управляются данной моделью.
     *
     * Используется в:
     *  - update()
     *  - toArray()
     *
     * По умолчанию пуст — рекомендуется переопределить в наследнике.
     *
     * @return string[]
     */
    protected function fields(): array
    {
        return [];
    }

    /**
     * Массовое обновление полей модели.
     *
     * Принимает массив локальных имён полей => значения, например:
     *
     *  [
     *      'unpaid_auto_delete'  => 1,
     *      'unpaid_time_minute'  => 15,
     *  ]
     *
     * Внутри:
     *  - фильтрует поля по fields();
     *  - строит payload с полными ключами "{prefix}.{field}";
     *  - вызывает iEXSetting(...) с array для массового сохранения.
     *
     * @param array<string,mixed> $data
     */
    public function update(array $data): void
    {
        $allowed = $this->fields();
        $payload = [];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }

            $payload[$this->key((string) $field)] = $value;
        }

        if ($payload !== []) {
            iEXSetting($payload, scope: $this->scope);
        }
    }

    /**
     * Собрать массив всех полей, указанных в fields().
     *
     * Пример результата:
     *
     *  [
     *      'unpaid_auto_delete' => 1,
     *      'unpaid_time_minute' => 15,
     *  ]
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->fields() as $field) {
            $result[$field] = DynamicConfig::get(
                $this->key($field),
                null,
                $this->scope
            );
        }

        return $result;
    }
}
