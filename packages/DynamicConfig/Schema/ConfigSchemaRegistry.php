<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Schema;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Класс ConfigSchemaRegistry
 *
 * Реестр схемы настроек DynamicConfig с поддержкой нескольких профилей.
 *
 * Назначение:
 *  - загружать определения схем (type, nullable, default, min/max, allowed, pattern, translation);
 *  - искать описание по ключу и профилю;
 *  - нормализовать и валидировать значение в соответствии со схемой.
 *
 * Структура схем:
 *
 *  storage/dynamic-config/schemas/
 *      default/
 *          security.php
 *          banners.php
 *          language.php
 *          ...
 *      plugins/
 *          bestchange.php
 *          bin_inspector.php
 *          ...
 *      language/
 *          language.php
 *          ...
 *
 * Каждый файл возвращает массив:
 *
 *  return [
 *      'security.is_google_auth' => [ 'type' => 'bool', 'default' => false, ... ],
 *      'banners.timeout'         => [ 'type' => 'int',  'min' => 0, 'max' => 600, ... ],
 *  ];
 *
 * Профиль схемы обычно привязан к scope_type:
 *  - global    → default
 *  - plugins   → папка plugins
 *  - language  → папка language
 */
final class ConfigSchemaRegistry
{
    /**
     * Базовый путь к директории с профилями схем.
     * Например: storage_path('dynamic-config/schemas').
     *
     * Ожидается структура:
     *  - {basePath}/default/*.php
     *  - {basePath}/{profile}/*.php
     *  - fallback: {basePath}/{profile}.php
     *
     * readonly — меняется только в конструкторе.
     */
    private readonly string $basePath;

    /**
     * Имя профиля по умолчанию (обычно "default").
     */
    private readonly string $defaultProfile;

    /**
     * Список поддерживаемых локалей (для type=translation).
     *
     * Например: ['ru', 'en'].
     *
     * @var string[]
     */
    private array $locales;

    /**
     * Кеш загруженных профилей:
     *
     * [
     *   'default' => [
     *       'exact'   => [ 'key' => definitionArray, ... ],
     *       'pattern' => [ 'features.*' => definitionArray, ... ],
     *   ],
     *   'plugins' => [...],
     *   'language'=> [...],
     * ]
     *
     * exact   — ключи без масок (строгое совпадение).
     * pattern — ключи с масками (*, ?) для Str::is().
     *
     * @var array<string, array{exact: array<string, array>, pattern: array<string, array>}>
     */
    private array $profiles = [];

    /**
     * @param string|null $basePath       Базовый путь к директории схем.
     * @param string      $defaultProfile Профиль по умолчанию (поддиректория).
     */
    public function __construct(
        ?string $basePath = null,
        string $defaultProfile = 'default'
    ) {
        $this->basePath       = $basePath ?? storage_path('dynamic-config/schemas');
        $this->defaultProfile = $defaultProfile;
        $this->locales        = config('dynamic_config.locales', ['ru', 'en']);
    }

    /**
     * Найти описание схемы для конкретного ключа и профиля.
     *
     * @param string      $key     Имя настройки (dot-notation).
     * @param string|null $profile Имя профиля схемы (если null — используется профиль по умолчанию).
     *
     * @return array<string,mixed>|null
     */
    public function findDefinition(string $key, ?string $profile = null): ?array
    {
        $profile = $profile ?: $this->defaultProfile;

        $definitions = $this->getProfileDefinitions($profile);

        // 1. Точное совпадение
        if (array_key_exists($key, $definitions['exact'])) {
            return $definitions['exact'][$key];
        }

        // 2. Маски (features.*, translations.*, etc.)
        foreach ($definitions['pattern'] as $pattern => $definition) {
            if (Str::is($pattern, $key)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Нормализует и валидирует значение в соответствии со схемой профиля.
     *
     * Если схема не найдена — возвращает значение как есть.
     * Если значение не проходит валидацию — выбрасывает \InvalidArgumentException.
     *
     * @param string      $key     Ключ настройки.
     * @param mixed       $value   Входное значение.
     * @param string|null $profile Имя профиля (по умолчанию defaultProfile).
     *
     * @return mixed Нормализованное значение.
     */
    public function normalizeAndValidate(string $key, mixed $value, ?string $profile = null): mixed
    {
        $definition = $this->findDefinition($key, $profile);

        if ($definition === null) {
            // Нет схемы — ничего не делаем
            return $value;
        }

        $type     = $definition['type'] ?? 'mixed';
        $nullable = (bool) ($definition['nullable'] ?? false);

        if ($value === null) {
            if ($nullable) {
                return null;
            }

            if (array_key_exists('default', $definition)) {
                return $definition['default'];
            }

            throw new \InvalidArgumentException(
                sprintf(
                    'Значение настройки "%s" не может быть null (профиль схемы: %s).',
                    $key,
                    $profile ?? $this->defaultProfile
                )
            );
        }

        // Приведение типов
        $normalized = $this->castToType($type, $value, $key, $profile);

        // Валидация числовых границ
        if (is_int($normalized) || is_float($normalized)) {
            $normalized = $this->validateNumericBounds($key, $normalized, $definition, $profile);
        }

        // Валидация допустимых значений (enum)
        if (array_key_exists('allowed', $definition)) {
            $this->validateAllowed($key, $normalized, (array) $definition['allowed'], $profile);
        }

        // Валидация по шаблону (pattern)
        if (is_string($normalized) && !empty($definition['pattern'] ?? '')) {
            $this->validatePattern($key, $normalized, (string) $definition['pattern'], $profile);
        }

        // Дополнительная проверка type=translation
        if ($type === 'translation') {
            $this->validateTranslationValue($key, $normalized, $profile);
        }

        return $normalized;
    }

    /**
     * Вернуть все точные определения схем для профиля.
     *
     * Используется, например, для поиска обязательных ключей (required).
     *
     * @param string|null $profile
     *
     * @return array<string,array<string,mixed>>
     */
    public function allDefinitions(?string $profile = null): array
    {
        $profile = $profile ?: $this->defaultProfile;

        $defs = $this->getProfileDefinitions($profile);

        return $defs['exact'];
    }

    // ------------------------------------------------------------------
    // Загрузка/кеширование профилей
    // ------------------------------------------------------------------

    /**
     * Загрузить определения схемы для профиля и разложить на:
     *  - exact   — точные ключи;
     *  - pattern — ключи-шаблоны (с масками).
     *
     * @param string $profile
     *
     * @return array{exact: array<string, array>, pattern: array<string, array>}
     */
    private function getProfileDefinitions(string $profile): array
    {
        if (isset($this->profiles[$profile])) {
            return $this->profiles[$profile];
        }

        $rawDefinitions = [];

        // 1. Базовый профиль default/
        $defaultDir = $this->basePath . DIRECTORY_SEPARATOR . $this->defaultProfile;
        if (File::isDirectory($defaultDir)) {
            foreach (File::files($defaultDir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $defs = require $file->getPathname();
                if (is_array($defs)) {
                    $rawDefinitions = array_merge($rawDefinitions, $defs);
                }
            }
        }

        // 2. Профиль по имени (например plugins/, language/)
        $profileDir = $this->basePath . DIRECTORY_SEPARATOR . $profile;
        if (File::isDirectory($profileDir)) {
            foreach (File::files($profileDir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $defs = require $file->getPathname();
                if (is_array($defs)) {
                    // Схема из профиля переопределяет default
                    $rawDefinitions = array_merge($rawDefinitions, $defs);
                }
            }
        }

        // 3. Fallback: одиночный файл basePath/{profile}.php
        $singleFile = $this->basePath . DIRECTORY_SEPARATOR . $profile . '.php';
        if (empty($rawDefinitions) && File::exists($singleFile)) {
            $defs = require $singleFile;
            if (is_array($defs)) {
                $rawDefinitions = $defs;
            }
        }

        $exact   = [];
        $pattern = [];

        foreach ($rawDefinitions as $key => $definition) {
            $key = (string) $key;

            if (str_contains($key, '*') || str_contains($key, '?')) {
                $pattern[$key] = $definition;
            } else {
                $exact[$key] = $definition;
            }
        }

        return $this->profiles[$profile] = [
            'exact'   => $exact,
            'pattern' => $pattern,
        ];
    }

    // ------------------------------------------------------------------
    // Приведение типов и проверки
    // ------------------------------------------------------------------

    /**
     * Приведение значения к типу, указанному в схеме.
     *
     * Поддерживаемые типы:
     *  - string
     *  - int
     *  - float
     *  - bool
     *  - array
     *  - json      (храним как array)
     *  - enum      (проверяется только allowed)
     *  - translation (массив локаль => строка)
     */
    private function castToType(string $type, mixed $value, string $key, ?string $profile): mixed
    {
        return match ($type) {
            'string'      => $this->castToString($value, $key, $profile),
            'int'         => $this->castToInt($value, $key, $profile),
            'float'       => $this->castToFloat($value, $key, $profile),
            'bool'        => $this->castToBool($value),
            'array'       => $this->castToArray($value, $key, $profile),
            'json'        => $this->castToJson($value, $key, $profile),
            'enum'        => $value, // проверим allowed отдельно
            'translation' => $this->castToArray($value, $key, $profile),
            default       => $value,
        };
    }

    private function castToString(mixed $value, string $key, ?string $profile): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Значение "%s" (профиль "%s") должно быть строкой.',
                $key,
                $profile ?? $this->defaultProfile
            )
        );
    }

    private function castToInt(mixed $value, string $key, ?string $profile): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Значение "%s" (профиль "%s") должно быть целым числом.',
                $key,
                $profile ?? $this->defaultProfile
            )
        );
    }

    private function castToFloat(mixed $value, string $key, ?string $profile): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Значение "%s" (профиль "%s") должно быть числом.',
                $key,
                $profile ?? $this->defaultProfile
            )
        );
    }

    private function castToBool(mixed $value): bool
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

    /**
     * @return array<mixed>
     */
    private function castToArray(mixed $value, string $key, ?string $profile): array
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
                // ниже выбросим исключение
            }
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Значение "%s" (профиль "%s") должно быть массивом или JSON-строкой.',
                $key,
                $profile ?? $this->defaultProfile
            )
        );
    }

    /**
     * @return array<mixed>
     */
    private function castToJson(mixed $value, string $key, ?string $profile): array
    {
        // Для type=json храним как ассоциативный массив (storage сам сериализует)
        return $this->castToArray($value, $key, $profile);
    }

    /**
     * Проверка числовых границ (min/max).
     */
    private function validateNumericBounds(
        string $key,
        float|int $value,
        array $definition,
        ?string $profile
    ): float|int {
        if (array_key_exists('min', $definition) && $value < $definition['min']) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Значение "%s" (%.4f) меньше минимально допустимого (%.4f), профиль "%s".',
                    $key,
                    $value,
                    $definition['min'],
                    $profile ?? $this->defaultProfile
                )
            );
        }

        if (array_key_exists('max', $definition) && $value > $definition['max']) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Значение "%s" (%.4f) больше максимально допустимого (%.4f), профиль "%s".',
                    $key,
                    $value,
                    $definition['max'],
                    $profile ?? $this->defaultProfile
                )
            );
        }

        return $value;
    }

    /**
     * Проверка, что значение входит в список allowed (enum).
     *
     * @param array<mixed> $allowed
     */
    private function validateAllowed(
        string $key,
        mixed $value,
        array $allowed,
        ?string $profile
    ): void {
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Значение настройки "%s" (профиль "%s") должно быть одним из: %s.',
                    $key,
                    $profile ?? $this->defaultProfile,
                    implode(', ', array_map(static fn ($v) => (string) $v, $allowed))
                )
            );
        }
    }

    private function validatePattern(
        string $key,
        string $value,
        string $pattern,
        ?string $profile
    ): void {
        if (@preg_match($pattern, '') === false) {
            Log::warning('DynamicConfig: некорректный pattern в схеме', [
                'key'     => $key,
                'pattern' => $pattern,
                'profile' => $profile ?? $this->defaultProfile,
            ]);

            return;
        }

        if (!preg_match($pattern, $value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Значение настройки "%s" не соответствует ожидаемому формату (профиль "%s").',
                    $key,
                    $profile ?? $this->defaultProfile
                )
            );
        }
    }

    /**
     * Дополнительная проверка для type=translation.
     */
    private function validateTranslationValue(
        string $key,
        mixed $value,
        ?string $profile
    ): void {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Значение "%s" (type=translation, профиль "%s") должно быть массивом.',
                    $key,
                    $profile ?? $this->defaultProfile
                )
            );
        }

        $unknownLocales = array_diff(array_keys($value), $this->locales);

        if (!empty($unknownLocales)) {
            Log::warning('DynamicConfig: translation содержит неизвестные локали', [
                'key'     => $key,
                'profile' => $profile ?? $this->defaultProfile,
                'locales' => $unknownLocales,
            ]);
        }
    }
}
