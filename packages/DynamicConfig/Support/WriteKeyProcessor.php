<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Support;

use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Класс WriteKeyProcessor
 *
 * Отвечает за обработку одного ключа при записи:
 *  - проверка прав на запись (ACL) через переданный callback;
 *  - нормализация значения через переданный callback (обычно normalizeWithSchema());
 *  - пост-обработка результата (например, trim строк).
 *
 * Не знает про БД, схемы или storage — только про key/value/scope.
 */
final class WriteKeyProcessor
{
    /**
     * @param array<string,mixed>|null $schemaInline  Инлайновая схема для текущего вызова.
     * @param string|null              $schemaProfile Профиль схемы (profile), если используется.
     */
    public function __construct(
        private readonly ?array $schemaInline,
        private readonly ?string $schemaProfile,
    ) {
    }

    /**
     * Полный обработчик одного ключа при записи.
     *
     * Возвращает массив:
     *  [
     *      'key'   => string,
     *      'value' => mixed, // нормализованное значение
     *  ]
     *
     * или null, если:
     *  - нет прав на запись (canWriteCallback вернул false),
     *  - нормализация завершилась ошибкой и была обработана выше.
     *
     * @param string        $key               Ключ настройки.
     * @param mixed         $value             Входное значение.
     * @param Scope         $scope             Текущий scope (global/user/license/...).
     * @param callable|null $canWriteCallback  Функция проверки прав:
     *                                         fn(string $key, Scope $scope): bool
     * @param callable      $normalizeCallback Функция нормализации:
     *                                         fn(string $key, mixed $value, ?string $profile, ?array $inline, Scope $scope): mixed
     *
     * @return array{key:string,value:mixed}|null
     */
    public function process(
        string $key,
        mixed $value,
        Scope $scope,
        ?callable $canWriteCallback,
        callable $normalizeCallback
    ): ?array {
        // 1. ACL: если callback передан, проверяем права записи
        if ($canWriteCallback !== null) {
            $canWrite = (bool) $canWriteCallback($key, $scope);

            if (!$canWrite) {
                return null;
            }
        }

        // 2. Нормализация значения через схему/валидатор
        $normalized = $normalizeCallback(
            $key,
            $value,
            $this->schemaProfile,
            $this->schemaInline,
            $scope
        );

        // 3. Дополнительная пост-обработка:
        //    - trim строк
        //    - при необходимости можно добавить собственные правила
        if (is_string($normalized)) {
            $normalized = trim($normalized);
        }

        return [
            'key'   => $key,
            'value' => $normalized,
        ];
    }
}
