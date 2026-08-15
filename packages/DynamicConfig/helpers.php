<?php

declare(strict_types=1);

use iEXPackages\DynamicConfig\DynamicConfigManager;
use iEXPackages\DynamicConfig\Facades\DynamicConfig;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Facades\Log;

if (!function_exists('iEXSetting')) {
    /**
     * Глобальный хелпер для работы с DynamicConfig.
     *
     * Назначение — тонкая обёртка над DynamicConfigManager с двумя основными сценариями:
     *
     * 1) ЧТЕНИЕ
     *    ------------------------------------------------------------------
     *    iEXSetting()
     *      → вернуть все настройки (merged = true) для текущего scope.
     *
     *    iEXSetting('key')
     *    iEXSetting('key', 'default')
     *    iEXSetting('key', 'default', 'ru')
     *    iEXSetting('key', 'default', 'ru', $scope)
     *      → чтение одного ключа с учётом цепочки scope (global → ... → current)
     *        и локали (если значение является translation-массивом).
     *
     * 2) МАССОВАЯ ЗАПИСЬ
     *    ------------------------------------------------------------------
     *    iEXSetting([
     *        'key1' => 'value1',
     *        'key2' => 'value2',
     *    ])
     *
     *    iEXSetting(
     *        ['key1' => 'value1', 'key2' => 'value2'],
     *        schemaInline: [
     *            'key1' => ['type' => 'int',  'min' => 0],
     *            'key2' => ['type' => 'bool'],
     *        ]
     *    )
     *
     *    В этом случае:
     *      - null-значения будут отброшены (array_filter);
     *      - для каждого ключа пройдёт pipeline:
     *          ACL → lock/protected → schema → storage → cache → versioning.
     *      - используется DynamicConfigManager::update().
     *
     * ПАРАМЕТРЫ:
     *
     * @param string|array|null   $key
     *      - null    → вернуть все настройки (merged);
     *      - string  → имя ключа для чтения;
     *      - array   → "ключ => значение" для массового обновления.
     *
     * @param mixed               $default
     *      Значение по умолчанию при чтении одиночного ключа.
     *
     * @param string|null         $locale
     *      Локаль для локализованных значений (["ru" => "...","en" => "..."]).
     *
     * @param Scope|array|null    $scope
     *      Scope для чтения/записи:
     *        - null      → текущий scope;
     *        - Scope     → объект Scope;
     *        - array     → ['type' => 'user', 'id' => 123].
     *
     * @param string|null         $schemaProfile
     *      Имя профиля схемы.
     *
     * @param array<string,mixed>|null $schemaInline
     *      Инлайновая схема для конкретного вызова.
     *
     * @return mixed
     *      - при чтении:
     *          → значение или $default;
     *          → массив всех настроек (при $key === null);
     *      - при записи:
     *          → true  при успешном обновлении;
     *          → false при ошибке или пустом наборе данных.
     */
    function iEXSetting(
        string|array|null $key = null,
        mixed $default = null,
        ?string $locale = null,
        Scope|array|null $scope = null,
        ?string $schemaProfile = null,
        ?array $schemaInline = null
    ): mixed {
        /** @var DynamicConfigManager|null $manager */
        static $manager = null;

        try {
            if ($manager === null) {
                $manager = app(DynamicConfigManager::class);
            }
        } catch (\Throwable $e) {
            Log::critical('DynamicConfigManager недоступен', [
                'message' => $e->getMessage(),
            ]);

            if ($key === null) {
                return [];
            }

            if (is_array($key)) {
                return false;
            }

            return $default;
        }

        // Нормализуем scope, если передан массив:
        if (is_array($scope)) {
            $type = $scope['type'] ?? 'global';
            $id   = $scope['id'] ?? null;

            $scope = Scope::fromString((string) $type, $id !== null ? (int) $id : null);
        }

        // МАССОВОЕ ОБНОВЛЕНИЕ
        if (is_array($key)) {
            if ($key === []) {
                return false;
            }

            // Нормализация входа:
            // - null НЕ фильтруем (null используется как сигнал "очистить/сбросить");
            // - строки триммим (включая вложенные массивы переводов).
            $data = (function (array $input): array {
                $normalize = function (mixed $value) use (&$normalize): mixed {
                    if (is_string($value)) {
                        return trim($value);
                    }

                    if (is_array($value)) {
                        $out = [];
                        foreach ($value as $k => $v) {
                            $out[$k] = $normalize($v);
                        }
                        return $out;
                    }

                    return $value;
                };

                $out = [];
                foreach ($input as $k => $v) {
                    $out[$k] = $normalize($v);
                }

                return $out;
            })($key);

            try {
                $manager->update($data, $scope, $schemaProfile, $schemaInline);
                return true;
            } catch (\Throwable $e) {
                Log::error('DynamicConfig: ошибка при массовом обновлении через iEXSetting()', [
                    'keys'          => array_keys($data),
                    'schemaProfile' => $schemaProfile,
                    'error'         => $e->getMessage(),
                ]);

                return false;
            }
        }

        // БЕЗ КЛЮЧА — вернуть все настройки
        if ($key === null) {
            return $manager->all($scope, true);
        }

        // Одиночное чтение
        return $manager->get($key, $default, $scope, $locale);
    }
}

if (!function_exists('iEXSettingInt')) {
    /**
     * Упрощённый helper для чтения int-значений.
     */
    function iEXSettingInt(string $key, int $default = 0, Scope|array|null $scope = null): int
    {
        /** @var DynamicConfigManager $manager */
        $manager = app(DynamicConfigManager::class);

        if (is_array($scope)) {
            $scope = Scope::fromString($scope['type'] ?? 'global', $scope['id'] ?? null);
        }

        return $manager->int($key, $default, $scope);
    }
}

if (!function_exists('iEXContentLanguage')) {
    /**
     * Многоязычные текстовые настройки (переводы) через DynamicConfig.
     *
     * Режимы:
     *
     * 1) iEXContentLanguage()
     *    → вернуть массив всех language.* ключей для scope language:1:
     *       [
     *         'sitename'       => ['ru' => '...', 'en' => '...'],
     *         'welcome_title'  => ['ru' => '...', 'en' => '...'],
     *         ...
     *       ]
     *
     * 2) iEXContentLanguage('sitename')
     *    → вернуть строку для текущей локали (например, "iEXBeta12").
     *
     *    iEXContentLanguage('sitename', 'en')
     *    → вернуть строку для конкретной локали.
     *
     * 3) RAW-режим:
     *    iEXContentLanguage('sitename', raw: true)
     *    → вернуть массив ['ru' => '...', 'en' => '...'] для ключа language.sitename.
     *
     * 4) Массовое обновление:
     *    iEXContentLanguage([
     *        'sitename'      => ['ru' => '...', 'en' => '...'],
     *        'welcome_title' => ['ru' => '...', 'en' => '...'],
     *    ])
     *
     * @param string|array|null $key
     * @param string|null       $locale
     * @param mixed             $default
     * @param bool              $raw
     *
     * @return mixed
     */
    function iEXContentLanguage(
        string|array|null $key = null,
        ?string $locale = null,
        mixed $default = null,
        bool $raw = false
    ): mixed {
        // Scope языка: language:1 (ID можно вынести в конфиг)
        $scope = Scope::fromString('language', 1);

        // МАССОВОЕ ОБНОВЛЕНИЕ LANGUAGE.*
        if (is_array($key)) {
            $data = [];

            foreach ($key as $field => $value) {
                $data['language.' . $field] = $value;
            }

            if ($data === []) {
                return false;
            }

            try {
                DynamicConfig::update($data, $scope);
                return true;
            } catch (\Throwable $e) {
                Log::error('iEXContentLanguage: ошибка при массовом обновлении переводов', [
                    'keys'  => array_keys($data),
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        // БЕЗ КЛЮЧА — вернуть все language.* настройки
        if ($key === null) {
            $all = DynamicConfig::all($scope, merged: false);

            return $all['language'] ?? [];
        }

        $fullKey = 'language.' . $key;

        // RAW-режим — вернуть массив всех локалей
        if ($raw === true) {
            return DynamicConfig::getRaw($fullKey, $default, $scope);
        }

        // Обычный режим — вернуть строку по локали
        return DynamicConfig::string($fullKey, (string) $default, $scope, $locale);
    }
}
