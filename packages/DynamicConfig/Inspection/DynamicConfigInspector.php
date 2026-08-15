<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Inspection;

use iEXPackages\DynamicConfig\DynamicConfigManager;
use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Класс DynamicConfigInspector
 *
 * Назначение:
 *  - анализировать настройки для конкретного scope;
 *  - искать:
 *      - неизвестные ключи;
 *      - устаревшие (deprecated);
 *      - значения, которые можно нормализовать (normalizable_mismatch);
 *      - ошибки валидации (validation_error);
 *      - отсутствующие обязательные ключи (missing_required);
 *  - выполнять auto-fix (dynamic-config:cleanup).
 *
 * Формат issue:
 *
 *  [
 *      'scope_type'      => string,
 *      'scope_id'        => int|null,
 *      'key'             => string,
 *      'issue_type'      => 'unknown_key' | 'deprecated' | 'normalizable_mismatch' | 'validation_error' | 'missing_required',
 *      'message'         => string,
 *      'current_value'   => mixed,
 *      'suggested_value' => mixed|null,
 *      'definition'      => array|null,
 *  ]
 */
final class DynamicConfigInspector
{
    public function __construct(
        private readonly DynamicConfigManager $configManager,
        private readonly ConfigSchemaRegistry $schemaRegistry
    ) {
    }

    /**
     * ПРОВЕРКА: собрать список проблем для конкретного scope.
     *
     * @param Scope       $scope
     * @param string|null $schemaProfile Профиль схемы (опционально).
     *
     * @return array<int,array<string,mixed>> Список issues.
     */
    public function inspectScope(Scope $scope, ?string $schemaProfile = null): array
    {
        $issues = [];

        // Берём только локальные настройки конкретного scope (без наследования)
        $settings = $this->configManager->all($scope, merged: false);

        // Плоский вид: key => value
        $flatSettings = Arr::dot($settings);

        // 1. Проверяем существующие ключи
        foreach ($flatSettings as $key => $value) {
            $definition = $this->schemaRegistry->findDefinition((string) $key, $schemaProfile);

            // 1.1. Неизвестный ключ
            if ($definition === null) {
                $issues[] = [
                    'scope_type'      => $scope->type,
                    'scope_id'        => $scope->id,
                    'key'             => (string) $key,
                    'issue_type'      => 'unknown_key',
                    'message'         => 'Ключ не описан в схеме настроек.',
                    'current_value'   => $value,
                    'suggested_value' => null,
                    'definition'      => null,
                ];
                continue;
            }

            // 1.2. deprecated
            if (!empty($definition['deprecated'] ?? false)) {
                $msg = 'Ключ помечен как устаревший (deprecated).';
                if (!empty($definition['alias_of'] ?? null)) {
                    $msg .= ' Используйте ключ: ' . $definition['alias_of'];
                }

                $issues[] = [
                    'scope_type'      => $scope->type,
                    'scope_id'        => $scope->id,
                    'key'             => (string) $key,
                    'issue_type'      => 'deprecated',
                    'message'         => $msg,
                    'current_value'   => $value,
                    'suggested_value' => null,
                    'definition'      => $definition,
                ];
            }

            // 1.3. Проверка по схеме (тип/границы/enum и т.д.)
            try {
                $normalized = $this->schemaRegistry->normalizeAndValidate((string) $key, $value, $schemaProfile);

                if ($normalized !== $value) {
                    $issues[] = [
                        'scope_type'      => $scope->type,
                        'scope_id'        => $scope->id,
                        'key'             => (string) $key,
                        'issue_type'      => 'normalizable_mismatch',
                        'message'         => 'Значение можно нормализовать (тип/формат отличается от ожидаемого).',
                        'current_value'   => $value,
                        'suggested_value' => $normalized,
                        'definition'      => $definition,
                    ];
                }
            } catch (\Throwable $e) {
                $issues[] = [
                    'scope_type'      => $scope->type,
                    'scope_id'        => $scope->id,
                    'key'             => (string) $key,
                    'issue_type'      => 'validation_error',
                    'message'         => 'Ошибка валидации по схеме: ' . $e->getMessage(),
                    'current_value'   => $value,
                    'suggested_value' => null,
                    'definition'      => $definition,
                ];
            }
        }

        // 2. Проверяем обязательные ключи (required) из схемы
        $definitions = $this->schemaRegistry->allDefinitions($schemaProfile);

        foreach ($definitions as $key => $definition) {
            if (empty($definition['required'] ?? false)) {
                continue;
            }

            if (!array_key_exists($key, $flatSettings)) {
                $default = $definition['default'] ?? null;

                $issues[] = [
                    'scope_type'      => $scope->type,
                    'scope_id'        => $scope->id,
                    'key'             => $key,
                    'issue_type'      => 'missing_required',
                    'message'         => 'Обязательный ключ отсутствует.',
                    'current_value'   => null,
                    'suggested_value' => $default,
                    'definition'      => $definition,
                ];
            }
        }

        return $issues;
    }

    /**
     * АВТОФИКС: попытаться автоматически исправить проблемы в конкретном scope.
     *
     * Настройки options:
     *  - remove_unknown      => bool (удалять неизвестные ключи)
     *  - remove_deprecated   => bool (удалять устаревшие ключи без alias_of)
     *  - fix_aliases         => bool (переносить значения с deprecated на alias_of)
     *  - fix_normalizable    => bool (сохранять нормализованные значения)
     *  - fill_defaults       => bool (заполнять отсутствующие required ключи дефолтами)
     *
     * Результат:
     *  [
     *      'fixed'   => [ ... ],
     *      'skipped' => [ ... ],
     *  ]
     */
    public function autoFixScope(
        Scope $scope,
        ?string $schemaProfile = null,
        array $options = []
    ): array {
        $options = array_merge([
            'remove_unknown'    => false,
            'remove_deprecated' => false,
            'fix_aliases'       => true,
            'fix_normalizable'  => true,
            'fill_defaults'     => true,
        ], $options);

        $issues = $this->inspectScope($scope, $schemaProfile);

        $fixed   = [];
        $skipped = [];

        foreach ($issues as $issue) {
            $key        = (string) ($issue['key'] ?? '');
            $type       = (string) ($issue['issue_type'] ?? '');
            $definition = $issue['definition'] ?? null;

            try {
                switch ($type) {
                    case 'unknown_key':
                        if ($options['remove_unknown']) {
                            $this->configManager->delete($key, $scope);
                            $fixed[] = [
                                'key'     => $key,
                                'action'  => 'removed_unknown_key',
                                'message' => 'Удалён неизвестный ключ.',
                            ];
                        } else {
                            $skipped[] = [
                                'key'     => $key,
                                'reason'  => 'unknown_key_no_remove',
                                'message' => 'Неизвестный ключ, но удаление отключено.',
                            ];
                        }
                        break;

                    case 'deprecated':
                        if (!is_array($definition)) {
                            $skipped[] = [
                                'key'     => $key,
                                'reason'  => 'deprecated_no_definition',
                                'message' => 'Ключ помечен как deprecated, но definition отсутствует.',
                            ];
                            break;
                        }

                        $aliasOf = $definition['alias_of'] ?? null;

                        if ($aliasOf && $options['fix_aliases']) {
                            $currentValue = $issue['current_value'] ?? null;

                            $this->configManager->set($aliasOf, $currentValue, $scope);

                            if ($options['remove_deprecated']) {
                                $this->configManager->delete($key, $scope);
                                $fixed[] = [
                                    'key'     => $key,
                                    'action'  => 'moved_to_alias_and_removed',
                                    'message' => sprintf(
                                        'Значение перенесено в "%s" и старый ключ удалён.',
                                        $aliasOf
                                    ),
                                ];
                            } else {
                                $fixed[] = [
                                    'key'     => $key,
                                    'action'  => 'moved_to_alias',
                                    'message' => sprintf(
                                        'Значение перенесено в "%s", но старый ключ оставлен.',
                                        $aliasOf
                                    ),
                                ];
                            }
                        } else {
                            if ($options['remove_deprecated']) {
                                $this->configManager->delete($key, $scope);
                                $fixed[] = [
                                    'key'     => $key,
                                    'action'  => 'removed_deprecated',
                                    'message' => 'Удалён устаревший ключ без alias_of.',
                                ];
                            } else {
                                $skipped[] = [
                                    'key'     => $key,
                                    'reason'  => 'deprecated_no_remove',
                                    'message' => 'Ключ deprecated, но удаление отключено.',
                                ];
                            }
                        }
                        break;

                    case 'normalizable_mismatch':
                        if ($options['fix_normalizable']) {
                            $suggested = $issue['suggested_value'] ?? null;

                            $this->configManager->set($key, $suggested, $scope);
                            $fixed[] = [
                                'key'     => $key,
                                'action'  => 'normalized_value',
                                'message' => 'Значение сохранено в нормализованном виде по схеме.',
                            ];
                        } else {
                            $skipped[] = [
                                'key'     => $key,
                                'reason'  => 'normalizable_no_fix',
                                'message' => 'Нормализация возможна, но авто-фикса нет.',
                            ];
                        }
                        break;

                    case 'missing_required':
                        if ($options['fill_defaults']) {
                            $suggested = $issue['suggested_value'] ?? null;

                            if ($suggested !== null) {
                                $this->configManager->set($key, $suggested, $scope);
                                $fixed[] = [
                                    'key'     => $key,
                                    'action'  => 'filled_required_with_default',
                                    'message' => 'Обязательный ключ заполнен значением по умолчанию.',
                                ];
                            } else {
                                $skipped[] = [
                                    'key'     => $key,
                                    'reason'  => 'missing_required_no_default',
                                    'message' => 'Обязательный ключ отсутствует, и default не задан.',
                                ];
                            }
                        } else {
                            $skipped[] = [
                                'key'     => $key,
                                'reason'  => 'missing_required_no_fill',
                                'message' => 'Обязательный ключ отсутствует, но fill_defaults=false.',
                            ];
                        }
                        break;

                    default:
                        $skipped[] = [
                            'key'     => $key,
                            'reason'  => 'unsupported_issue_type_for_autofix',
                            'message' => 'Тип проблемы не поддерживается для авто-фикса: ' . $type,
                        ];
                        break;
                }
            } catch (\Throwable $e) {
                Log::error('DynamicConfigInspector: ошибка при авто-фиксе', [
                    'key'        => $key,
                    'issue_type' => $type,
                    'error'      => $e->getMessage(),
                ]);

                $skipped[] = [
                    'key'     => $key,
                    'reason'  => 'exception_during_fix',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'fixed'   => $fixed,
            'skipped' => $skipped,
        ];
    }
}
