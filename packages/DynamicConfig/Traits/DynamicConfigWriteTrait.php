<?php

namespace iEXPackages\DynamicConfig\Traits;

use iEXPackages\DynamicConfig\Events\DynamicConfigScopeClearedEvent;
use iEXPackages\DynamicConfig\Events\DynamicConfigSettingSetEvent;
use iEXPackages\DynamicConfig\Events\DynamicConfigSettingsUpdatedEvent;
use iEXPackages\DynamicConfig\Models\DynamicConfigLock;
use iEXPackages\DynamicConfig\Models\DynamicConfigSetting;
use iEXPackages\DynamicConfig\Support\WriteKeyProcessor;
use iEXPackages\DynamicConfig\Support\DynamicConfigSystemContext;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trait DynamicConfigWriteTrait
 *
 * Отвечает за операции записи над настройками:
 *
 *  - set()/update()/delete()/clearScope()
 *  - setWithTtl()
 *  - setIfChanged(), setWithMode(), setIf()
 *  - setNamespaces(), renameKey(), copyKey(), moveNamespace(), deleteNamespace()
 *  - softDelete(), lockKey(), unlockKey()
 *  - history(), rollbackVersion(), logVersion()
 *
 * Предполагается, что:
 *  - хранилище поддерживает partial save в формате ['key' => value];
 *  - чтение настроек идёт через DynamicConfigReadTrait;
 *  - схема (schema) и ACL применяются через DynamicConfigSchemaAndLockTrait и ConfigAccessControl.
 */
trait DynamicConfigWriteTrait
{
    /**
     * Разрешена ли запись в настройки из системного контекста (консоль или DynamicConfigSystemContext).
     *
     * Используется только когда включён enforceWriteAccess и настроен ACL.
     * Управляется конфигом:
     *  - dynamic_config.access_control.console_writes_enabled (bool)
     *  - dynamic_config.access_control.console_write_whitelist (array of Str::is patterns)
     */
    private function canSystemWrite(string $key): bool
    {
        // 1. Проверяем глобальный флаг
        if (!(bool) config('dynamic_config.access_control.console_writes_enabled', true)) {
            return false;
        }

        // 2. Проверяем whitelist-паттерны
        $patterns = (array) config('dynamic_config.access_control.console_write_whitelist', []);
        $matched = false;
        foreach ($patterns as $pattern) {
            if (Str::is((string)$pattern, $key)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }

        // 3. Если выполняется в консоли — разрешаем
        if (app()->runningInConsole()) {
            return true;
        }

        // 4. В веб-контексте разрешаем только если включён DynamicConfigSystemContext
        /** @var DynamicConfigSystemContext $systemContext */
        $systemContext = app(DynamicConfigSystemContext::class);
        return $systemContext->isEnabled();
    }

    /**
     * Установить/обновить одно значение в конкретном scope.
     *
     * Применяет:
     *  - временные lock-и;
     *  - protected_keys;
     *  - locked по схеме (locked = true);
     *  - ACL на запись;
     *  - нормализацию по схеме (inline + global).
     *
     * @param string      $key           Ключ настройки.
     * @param mixed       $value         Новое значение.
     * @param Scope|null  $scope         Scope (если null — текущий).
     * @param string|null $schemaProfile Профиль схемы (может быть null).
     * @param array|null  $schemaInline  Инлайновая схема для этого вызова (по ключам).
     */
    public function set(
        string $key,
        mixed $value,
        ?Scope $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        // Блокировки ключа
        if ($this->isTemporarilyLocked($scope, $key)
            || $this->isProtectedKey($key)
            || $this->isLockedBySchema($key, $schemaProfile)) {
            return;
        }

        $user      = Auth::user();
        $processor = new WriteKeyProcessor($schemaInline, $schemaProfile);

        // ACL (права записи)
        $canWriteCallback = null;
        if ($this->enforceWriteAccess && $this->access !== null) {
            if ($user === null) {
                // В системном контексте (консоль или DynamicConfigSystemContext) разрешаем запись только для whitelisted ключей
                if (!$this->canSystemWrite($key)) {
                    $canWriteCallback = static fn (string $k, Scope $sc): bool => false;
                }
                // Если ключ разрешён — ACL не применяем (callback остаётся null)
            } else {
                $canWriteCallback = function (string $k, Scope $sc) use ($user, $schemaProfile): bool {
                    return $this->access->canWrite($k, $user, $schemaProfile);
                };
            }
        }

        try {
            $result = $processor->process(
                $key,
                $value,
                $scope,
                $canWriteCallback,
                fn (string $k, mixed $v, ?string $profile, ?array $inline, Scope $sc) =>
                $this->normalizeWithSchema($k, $v, $profile, $inline, $sc)
            );
        } catch (\Throwable $e) {
            Log::error('DynamicConfig: не удалось сохранить настройку (set) из-за ошибки валидации', [
                'key'     => $key,
                'profile' => $schemaProfile,
                'error'   => $e->getMessage(),
            ]);

            return;
        }

        if ($result === null) {
            return;
        }

        $normalizedValue = $result['value'];

        // Считываем старое значение только для версионирования и событий
        $settingsBefore = $this->getSettingsForExactScope($scope);
        $oldValue       = Arr::get($settingsBefore, $key);

        // Если значение не изменилось — выходим без записи и версионирования
        if ($oldValue === $normalizedValue) {
            return;
        }

        // Сохраняем только один ключ (partial update)
        $this->storage->save($scope, [
            $key => $normalizedValue,
        ]);

        // Очищаем кеш по scope после записи
        $this->forgetScopeCache($scope);

        // Логируем версию изменения
        $this->logVersion($scope, $key, $oldValue, $normalizedValue);

        // Генерируем событие set
        event(new DynamicConfigSettingSetEvent(
            scope: $scope,
            key: $key,
            oldValue: $oldValue,
            newValue: $normalizedValue
        ));
    }

    /**
     * Массовое обновление значений в конкретном scope.
     *
     * Важно:
     *  - обрабатываются только ключи, прошедшие lock/protected/ACL/schema;
     *  - сохраняются только ключи, чьи значения реально изменились;
     *  - версионирование и событие генерируются только по изменённым ключам.
     *
     * @param array<string,mixed> $data  Ассоциативный массив key => value.
     * @param Scope|null          $scope Scope (если null — текущий).
     * @param string|null         $schemaProfile
     * @param array|null          $schemaInline
     */
    public function update(
        array $data,
        ?Scope $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        // Старые значения для diff/истории
        $settingsBefore = $this->getSettingsForExactScope($scope);

        $user      = Auth::user();
        $processor = new WriteKeyProcessor($schemaInline, $schemaProfile);

        // ACL (права записи) — готовим один раз на весь update
        $canWriteCallback = null;
        if ($this->enforceWriteAccess && $this->access !== null) {
            if ($user === null) {
                // В системном контексте (консоль или DynamicConfigSystemContext) разрешаем запись только для whitelisted ключей
                $canWriteCallback = fn (string $k, Scope $sc): bool => $this->canSystemWrite($k);
            } else {
                $canWriteCallback = function (string $k, Scope $sc) use ($user, $schemaProfile): bool {
                    return $this->access->canWrite($k, $user, $schemaProfile);
                };
            }
        }

        $normalizedData = [];

        foreach ($data as $key => $value) {
            $key = (string) $key;

            // Lock/protected/schema-locked
            if ($this->isTemporarilyLocked($scope, $key)
                || $this->isProtectedKey($key)
                || $this->isLockedBySchema($key, $schemaProfile)) {
                continue;
            }


            try {
                $result = $processor->process(
                    $key,
                    $value,
                    $scope,
                    $canWriteCallback,
                    fn (string $k, mixed $v, ?string $profile, ?array $inline, Scope $sc) =>
                    $this->normalizeWithSchema($k, $v, $profile, $inline, $sc)
                );
            } catch (\Throwable $e) {

                Log::error('DynamicConfig: не удалось сохранить настройку (update) из-за ошибки валидации', [
                    'key'     => $key,
                    'profile' => $schemaProfile,
                    'error'   => $e->getMessage(),
                ]);
                continue;
            }


            if ($result === null) {
                continue;
            }

            $normalizedData[$result['key']] = $result['value'];
        }

        if ($normalizedData === []) {
            return;
        }

        // Фильтруем только действительно изменённые ключи
        $toSave  = [];
        $changes = [];

        foreach ($normalizedData as $key => $newValue) {
            $oldValue = Arr::get($settingsBefore, $key);

            if ($oldValue === $newValue) {
                continue;
            }

            $toSave[$key] = $newValue;
            $changes[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        // Если нет реальных изменений — ничего не делаем
        if ($toSave === []) {
            return;
        }

        // Сохраняем изменённые ключи
        $this->storage->save($scope, $toSave);
        $this->forgetScopeCache($scope);

        // Логируем версии
        foreach ($changes as $key => $change) {
            $this->logVersion($scope, $key, $change['old'], $change['new']);
        }

        // Генерируем событие обновления настроек
        event(new DynamicConfigSettingsUpdatedEvent(
            scope: $scope,
            changes: $changes
        ));
    }

    /**
     * Удалить один или несколько ключей (по умолчанию — soft-delete).
     *
     * @param string|string[] $keys
     * @param Scope|null      $scope
     */
    public function delete(
        string|array $keys,
        ?Scope $scope = null
    ): void {
        $this->softDelete($keys, $scope);
    }

    /**
     * Полностью очистить настройки конкретного scope (hard-delete всех записей для scope).
     *
     * Дополнительно:
     *  - логируются версии (old_value → null) для каждого удалённого ключа;
     *  - генерируется событие DynamicConfigScopeClearedEvent.
     */
    public function clearScope(Scope $scope): void
    {
        $oldSettings = $this->getSettingsForExactScope($scope);

        $this->storage->clear($scope);
        $this->forgetScopeCache($scope);

        $flat = Arr::dot($oldSettings);

        foreach ($flat as $key => $value) {
            $this->logVersion($scope, $key, $value, null);
        }

        event(new DynamicConfigScopeClearedEvent(
            scope: $scope,
            oldSettings: $oldSettings
        ));
    }

    /**
     * Установить значение ключа с TTL (expires_at).
     *
     * @param string                     $key
     * @param mixed                      $value
     * @param \DateTimeInterface|string  $expiresAt
     * @param Scope|null                 $scope
     * @param string|null                $schemaProfile
     * @param array|null                 $schemaInline
     */
    public function setWithTtl(
        string $key,
        mixed $value,
        \DateTimeInterface|string $expiresAt,
        ?Scope $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $this->set($key, $value, $scope, $schemaProfile, $schemaInline);

        $expires = $expiresAt instanceof \DateTimeInterface
            ? $expiresAt->format('Y-m-d H:i:s')
            : (string) $expiresAt;

        DB::table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->where('key', $key)
            ->update([
                'expires_at' => $expires,
                'updated_at' => now(),
            ]);

        $this->forgetScopeCache($scope);
    }

    /**
     * Переименовать ключ в рамках одного scope.
     *
     * @param string     $from      Исходный ключ.
     * @param string     $to        Новый ключ.
     * @param Scope|null $scope
     * @param bool       $deleteOld Удалить ли старый ключ после копирования.
     */
    public function renameKey(
        string $from,
        string $to,
        ?Scope $scope = null,
        bool $deleteOld = true
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        if ($this->isProtectedKey($from) || $this->isProtectedKey($to)) {
            Log::warning('DynamicConfig: renameKey заблокирован protected_keys', [
                'from'  => $from,
                'to'    => $to,
                'scope' => $scope->cacheKey(),
            ]);

            return;
        }

        $value = $this->get($from, null, $scope);

        if ($value === null) {
            return;
        }

        $this->set($to, $value, $scope);

        if ($deleteOld) {
            $this->delete($from, $scope);
        }
    }

    /**
     * Скопировать значение одного ключа в другой внутри одного scope.
     *
     * @param string     $from     Исходный ключ.
     * @param string     $to       Ключ-приёмник.
     * @param Scope|null $scope
     * @param bool       $override Перезаписывать ли значение, если $to уже существует.
     */
    public function copyKey(
        string $from,
        string $to,
        ?Scope $scope = null,
        bool $override = false
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        if ($this->isProtectedKey($from) || $this->isProtectedKey($to)) {
            Log::warning('DynamicConfig: copyKey заблокирован protected_keys', [
                'from'  => $from,
                'to'    => $to,
                'scope' => $scope->cacheKey(),
            ]);

            return;
        }

        $source = $this->get($from, null, $scope);

        if ($source === null) {
            return;
        }

        if (!$override) {
            $existing = $this->get($to, null, $scope);

            if ($existing !== null) {
                return;
            }
        }

        $this->set($to, $source, $scope);
    }

    /**
     * Переместить namespace: {fromPrefix}.* → {toPrefix}.*.
     *
     * @param string     $fromPrefix Текущий префикс.
     * @param string     $toPrefix   Новый префикс.
     * @param Scope|null $scope
     * @param bool       $deleteOld  Удалить ли старые ключи.
     */
    public function moveNamespace(
        string $fromPrefix,
        string $toPrefix,
        ?Scope $scope = null,
        bool $deleteOld = true
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $settings = $this->all($scope, merged: false);
        $flat     = Arr::dot($settings);

        foreach ($flat as $key => $value) {
            if (!str_starts_with($key, $fromPrefix . '.')) {
                continue;
            }

            $suffix = substr($key, strlen($fromPrefix . '.'));
            $newKey = $toPrefix . '.' . $suffix;

            if ($this->isProtectedKey($key) || $this->isProtectedKey($newKey)) {
                Log::warning('DynamicConfig: moveNamespace пропускает protected ключ', [
                    'old'   => $key,
                    'new'   => $newKey,
                    'scope' => $scope->cacheKey(),
                ]);

                continue;
            }

            $this->set($newKey, $value, $scope);

            if ($deleteOld) {
                $this->delete($key, $scope);
            }
        }
    }

    /**
     * Удалить все ключи, начинающиеся с указанного префикса (namespace).
     *
     * @param string     $prefix Префикс namespace.
     * @param Scope|null $scope
     */
    public function deleteNamespace(
        string $prefix,
        ?Scope $scope = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $settings = $this->all($scope, merged: false);
        $flat     = Arr::dot($settings);

        $keysToDelete = [];

        foreach (array_keys($flat) as $key) {
            if (!str_starts_with($key, $prefix . '.')) {
                continue;
            }

            if ($this->isProtectedKey($key)) {
                Log::warning('DynamicConfig: deleteNamespace пропускает protected ключ', [
                    'key'   => $key,
                    'scope' => $scope->cacheKey(),
                ]);
                continue;
            }

            $keysToDelete[] = $key;
        }

        if ($keysToDelete !== []) {
            $this->delete($keysToDelete, $scope);
        }
    }

    /**
     * Массовая запись нескольких namespace.
     *
     * Формат:
     *  [
     *      'bestchange' => ['api_key' => '...', 'timeout' => 10],
     *      'language'   => ['sitename' => [...], 'seo_description' => [...]],
     *  ]
     *
     * Внутри каждое значение разворачивается в плоский ключ "prefix.suffix".
     */
    public function setNamespaces(
        array $namespaces,
        ?Scope $scope = null,
        ?string $schemaProfile = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $data = [];

        foreach ($namespaces as $prefix => $values) {
            if (!is_array($values)) {
                continue;
            }

            foreach (Arr::dot($values) as $suffix => $value) {
                $data[$prefix . '.' . $suffix] = $value;
            }
        }

        if ($data === []) {
            return;
        }

        $this->update($data, $scope, $schemaProfile);
    }

    /**
     * Условная запись: set(), если $condition() === true.
     */
    public function setIf(
        callable $condition,
        string $key,
        mixed $value,
        ?Scope $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        if (!$condition()) {
            return;
        }

        $this->set($key, $value, $scope, $schemaProfile, $schemaInline);
    }

    /**
     * Записать значение, только если оно отличается от текущего.
     */
    public function setIfChanged(
        string $key,
        mixed $value,
        ?Scope $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $current = $this->get($key, null, $scope);

        if ($current === $value) {
            return;
        }

        $this->set($key, $value, $scope, $schemaProfile, $schemaInline);
    }

    /**
     * Расширенная запись с режимами:
     *  - replace        (по умолчанию)
     *  - merge          (для массивов: глубокий merge)
     *  - skip-existing  (не перезаписывать, если ключ уже есть)
     *  - force          (обойти lock/protected/schema-locked, но обновить только один ключ)
     */
    public function setWithMode(
        string $key,
        mixed $value,
        string $mode = 'replace',
        ?Scope $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();
        $mode  = strtolower($mode);

        // Режим force: игнорируем lock/protected/schema-locked, но уважаем схемы
        if ($mode === 'force') {
            $settingsBefore = $this->getSettingsForExactScope($scope);
            $oldValue       = Arr::get($settingsBefore, $key);

            if ($this->schema !== null) {
                $value = $this->schema->normalizeAndValidate($key, $value, $schemaProfile);
            }

            if ($oldValue === $value) {
                return;
            }

            $this->storage->save($scope, [$key => $value]);
            $this->forgetScopeCache($scope);

            $this->logVersion($scope, $key, $oldValue, $value ?? null);

            return;
        }

        $current = $this->get($key, null, $scope);

        if ($mode === 'skip-existing' && $current !== null) {
            return;
        }

        if ($mode === 'merge') {
            $currentArr = is_array($current) ? $current : [];
            $newArr     = is_array($value) ? $value : [];

            $merged = array_replace_recursive($currentArr, $newArr);

            $this->set($key, $merged, $scope, $schemaProfile, $schemaInline);

            return;
        }

        $this->set($key, $value, $scope, $schemaProfile, $schemaInline);
    }

    /**
     * Soft-delete заданных ключей (пометить deleted_at, не удаляя физически).
     *
     * @param string|string[] $keys
     * @param Scope|null      $scope
     */
    public function softDelete(
        string|array $keys,
        ?Scope $scope = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $keys = (array) $keys;

        DynamicConfigSetting::query()
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->whereIn('key', $keys)
            ->delete();

        $this->forgetScopeCache($scope);
    }

    /**
     * Заблокировать ключ до определённой даты или навсегда.
     */
    public function lockKey(
        string $key,
        ?Scope $scope = null,
        \DateTimeInterface|string|null $until = null,
        bool $permanent = false,
        ?string $reason = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $lockedUntil = null;

        if ($until !== null) {
            $lockedUntil = $until instanceof \DateTimeInterface
                ? $until
                : new \DateTimeImmutable((string) $until);
        }

        DynamicConfigLock::query()->updateOrCreate(
            [
                'scope_type' => $scope->type,
                'scope_id'   => $scope->id,
                'key'        => $key,
            ],
            [
                'locked_until'     => $permanent ? null : $lockedUntil,
                'locked_permanent' => $permanent,
                'reason'           => $reason,
                'created_by'       => Auth::id(),
                'created_at'       => now(),
            ]
        );
    }

    /**
     * Снять lock с ключа.
     */
    public function unlockKey(
        string $key,
        ?Scope $scope = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        DynamicConfigLock::query()
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->where('key', $key)
            ->delete();
    }

    /**
     * Безопасное чтение: вернуть маску, если ключ существует.
     *
     * @param string     $key
     * @param string     $mask  Строка-маска.
     * @param Scope|null $scope
     *
     * @return string|null Маска или null, если ключ не существует.
     */
    public function safe(
        string $key,
        string $mask = '***',
        ?Scope $scope = null
    ): ?string {
        $scope ??= $this->scopeResolver->currentScope();

        $exists = $this->get($key, null, $scope);

        if ($exists === null) {
            return null;
        }

        return $mask;
    }

    /**
     * История изменений ключа для указанного scope.
     *
     * @param string     $key
     * @param Scope|null $scope
     * @param int        $limit Максимальное количество записей.
     *
     * @return array<int,array<string,mixed>>
     */
    public function history(
        string $key,
        ?Scope $scope = null,
        int $limit = 50
    ): array {
        $scope ??= $this->scopeResolver->currentScope();

        $rows = DB::table('dynamic_config_versions')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->where('key', $key)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id'         => $row->id,
                'key'        => $row->key,
                'old_value'  => $row->old_value ? json_decode($row->old_value, true) : null,
                'new_value'  => $row->new_value ? json_decode($row->new_value, true) : null,
                'changed_by' => $row->changed_by,
                'created_at' => $row->created_at,
            ];
        }

        return $result;
    }

    /**
     * Откатить ключ к состоянию, зафиксированному в указанной версии.
     *
     * Если old_value = null — ключ будет удалён.
     */
    public function rollbackVersion(
        int $versionId,
        ?Scope $scope = null
    ): void {
        $scope ??= $this->scopeResolver->currentScope();

        $row = DB::table('dynamic_config_versions')
            ->where('id', $versionId)
            ->first();

        if (!$row) {
            return;
        }

        if ($row->scope_type !== $scope->type || (int) $row->scope_id !== $scope->id) {
            return;
        }

        $key      = (string) $row->key;
        $oldValue = $row->old_value ? json_decode($row->old_value, true) : null;

        if ($oldValue === null) {
            $this->delete($key, $scope);
        } else {
            $this->set($key, $oldValue, $scope);
        }
    }

    /**
     * Логирование версии изменения ключа.
     *
     * Учитывает конфиг dynamic_config.versions_enabled.
     */
    private function logVersion(
        Scope $scope,
        string $key,
        mixed $oldValue,
        mixed $newValue
    ): void {
        if (!config('dynamic_config.versions_enabled', true)) {
            return;
        }

        // Если ничего не изменилось — версию не пишем
        if ($oldValue === $newValue) {
            return;
        }

        try {
            $old = $oldValue === null
                ? null
                : json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $new = $newValue === null
                ? null
                : json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('DynamicConfig: не удалось сериализовать значения для версии', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $userId = Auth::id();

        DB::table('dynamic_config_versions')->insert([
            'scope_type' => $scope->type,
            'scope_id'   => $scope->id,
            'key'        => $key,
            'old_value'  => $old,
            'new_value'  => $new,
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
