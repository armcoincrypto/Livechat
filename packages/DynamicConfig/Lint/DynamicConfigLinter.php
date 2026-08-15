<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Lint;

use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use Illuminate\Support\Str;

/**
 * Linter схемы DynamicConfig + опционально конфигов.
 *
 * Issues:
 *  - schema_alias_unknown
 *  - schema_deprecated_locked
 *  - schema_missing_global_scope
 *  - config_key_without_schema
 */
final class DynamicConfigLinter
{
    public function __construct(
        private readonly ConfigSchemaRegistry $schemaRegistry
    ) {
    }

    /**
     * Проверка только схемы.
     */
    public function lintSchema(?string $schemaProfile = null): array
    {
        $issues = [];

        $definitions = $this->schemaRegistry->allDefinitions($schemaProfile);

        // 1. alias_of → должен ссылаться на существующий ключ или паттерн
        foreach ($definitions as $key => $def) {
            $aliasOf = $def['alias_of'] ?? null;

            if (!$aliasOf) {
                continue;
            }

            $targetDef = $this->schemaRegistry->findDefinition($aliasOf, $schemaProfile);

            if ($targetDef === null) {
                $issues[] = [
                    'type'    => 'schema_alias_unknown',
                    'key'     => $key,
                    'message' => sprintf(
                        'Ключ "%s" имеет alias_of="%s", который не найден в схеме.',
                        $key,
                        $aliasOf
                    ),
                ];
            }
        }

        // 2. deprecated + locked одновременно — подозрительно
        foreach ($definitions as $key => $def) {
            $deprecated = !empty($def['deprecated'] ?? false);
            $locked     = !empty($def['locked'] ?? false);

            if ($deprecated && $locked) {
                $issues[] = [
                    'type'    => 'schema_deprecated_locked',
                    'key'     => $key,
                    'message' => sprintf(
                        'Ключ "%s" помечен и как deprecated, и как locked — имеет ли это смысл?',
                        $key
                    ),
                ];
            }
        }

        // 3. Проверка, что global scope есть в конфиге
        $scopes = config('dynamic_config.scopes', []);
        if (!array_key_exists('global', $scopes)) {
            $issues[] = [
                'type'    => 'schema_missing_global_scope',
                'key'     => 'scopes.global',
                'message' => 'В config("dynamic_config.scopes") отсутствует ключ "global".',
            ];
        }

        return $issues;
    }

    /**
     * Проверка конфигов: ключи без схемы.
     *
     * @param bool $checkPatterns учитывать ли паттерны (features.*, translations.*)
     */
    public function lintConfigs(bool $checkPatterns = true, ?string $schemaProfile = null): array
    {
        $issues = [];

        $rows = \DB::table('dynamic_config_settings')
            ->select('scope_type', 'scope_id', 'key')
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->key;

            $def = $this->schemaRegistry->findDefinition($key, $schemaProfile);

            if ($def === null) {
                $issues[] = [
                    'type'       => 'config_key_without_schema',
                    'scope_type' => (string) $row->scope_type,
                    'scope_id'   => $row->scope_id,
                    'key'        => $key,
                    'message'    => 'Ключ присутствует в dynamic_config_settings, но отсутствует в схеме.',
                ];
            }
        }

        return $issues;
    }
}
