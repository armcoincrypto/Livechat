<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation\Rules;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class MetaRules
{
    /** @return ValidationError[] */
    public static function check(GatewayConfig $cfg, bool $strict): array
    {
        $out = [];

        $schema = $cfg->schema();
        if ($schema < 1) {
            $out[] = ValidationError::error('meta.schema.invalid', 'schema', 'schema должен быть >= 1');
        }

        $name = (string)($cfg->name() ?? '');
        if ($name === '') {
            $out[] = ValidationError::error('meta.name.required', 'meta.name', 'meta.name обязателен');
        }

        $alias = (string)($cfg->alias() ?? '');
        if ($alias === '') {
            $out[] = ValidationError::error('meta.alias.required', 'meta.alias', 'meta.alias обязателен');
        } else {
            // alias должен быть snake-like: letters, digits, underscore
            if (!preg_match('/^[a-z0-9_]+$/', $alias)) {
                $out[] = ValidationError::warning(
                    'meta.alias.format',
                    'meta.alias',
                    'meta.alias рекомендуется в формате snake_case (a-z0-9_)',
                    ['alias' => $alias]
                );
            }
        }

        $version = (string)($cfg->version() ?? '');
        if ($version === '') {
            $out[] = ValidationError::warning('meta.version.missing', 'meta.version', 'meta.version не указан');
        }

        $category = (string)($cfg->category() ?? '');
        if ($category === '') {
            $out[] = ValidationError::warning('meta.category.missing', 'meta.category', 'meta.category не указан (crypto/fiat/...)');
        }

        return $out;
    }
}
