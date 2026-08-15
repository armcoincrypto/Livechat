<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation\Rules;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class OptionsRules
{
    /** @return ValidationError[] */
    public static function check(GatewayConfig $cfg, bool $strict): array
    {
        $out = [];
        $all = $cfg->all();

        // Если где-то используется options_api_method → желательно иметь service operation "options"
        $usesOptionsApiMethod = false;

        foreach (['merchant', 'pay'] as $group) {
            $fields = $all['inputs'][$group]['options_fields'] ?? [];
            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $i => $f) {
                if (!is_array($f)) {
                    continue;
                }

                if (($f['type'] ?? null) === 'select' && !empty($f['options_api_method'])) {
                    $usesOptionsApiMethod = true;
                }
            }
        }

        if ($usesOptionsApiMethod) {
            $op = $cfg->operationConfig('options');
            if (empty($op)) {
                $out[] = ValidationError::warning(
                    'options.missing_operation',
                    'operations.options',
                    'В config.php используются options_api_method, но операция "options" не объявлена. UI не сможет получить список из API.'
                );
            } else {
                $type = (string)($op['type'] ?? '');
                if ($type !== 'service') {
                    $out[] = ValidationError::warning(
                        'options.operation.type',
                        'operations.options.type',
                        'Операция options рекомендуется с type=service'
                    );
                }
            }
        }

        return $out;
    }
}
