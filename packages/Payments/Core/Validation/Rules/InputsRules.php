<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation\Rules;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class InputsRules
{
    /** @return ValidationError[] */
    public static function check(GatewayConfig $cfg, bool $strict): array
    {
        $out = [];
        $all = $cfg->all();

        $inputs = $all['inputs'] ?? null;
        if (!is_array($inputs)) {
            $out[] = ValidationError::error('inputs.missing', 'inputs', 'inputs обязателен и должен быть массивом');
            return $out;
        }

        $caps = $cfg->capabilities();
        $incoming = (bool)($caps['incoming'] ?? false);
        $outgoing = (bool)($caps['outgoing'] ?? false);

        if ($incoming && !isset($inputs['merchant'])) {
            $out[] = ValidationError::error('inputs.merchant.required', 'inputs.merchant', 'incoming=true → inputs.merchant обязателен');
        }

        if ($outgoing && !isset($inputs['pay'])) {
            $out[] = ValidationError::error('inputs.pay.required', 'inputs.pay', 'outgoing=true → inputs.pay обязателен');
        }

        foreach (['merchant', 'pay'] as $group) {
            if (!isset($inputs[$group])) {
                continue;
            }

            if (!is_array($inputs[$group])) {
                $out[] = ValidationError::error("inputs.{$group}.type", "inputs.{$group}", "inputs.{$group} должен быть массивом");
                continue;
            }

            $fields = $inputs[$group]['fields'] ?? [];
            if (!is_array($fields)) {
                $out[] = ValidationError::error("inputs.{$group}.fields.type", "inputs.{$group}.fields", "inputs.{$group}.fields должен быть массивом");
                $fields = [];
            }

            $keys = [];
            foreach ($fields as $i => $field) {
                $p = "inputs.{$group}.fields[{$i}]";
                if (!is_array($field)) {
                    $out[] = ValidationError::warning('inputs.field.invalid', $p, 'Поле должно быть массивом');
                    continue;
                }

                $key = (string)($field['key'] ?? '');
                if ($key === '') {
                    $out[] = ValidationError::error('inputs.field.key.required', "{$p}.key", 'key обязателен');
                    continue;
                }

                if (isset($keys[$key])) {
                    $out[] = ValidationError::error(
                        'inputs.field.key.duplicate',
                        "{$p}.key",
                        "Дубликат key={$key} в inputs.{$group}.fields"
                    );
                }
                $keys[$key] = true;

                $type = (string)($field['type'] ?? '');
                if ($type === '') {
                    $out[] = ValidationError::warning('inputs.field.type.missing', "{$p}.type", 'type рекомендуется (input/select/...)');
                }

                $label = (string)($field['label'] ?? '');
                if ($label === '') {
                    $out[] = ValidationError::warning('inputs.field.label.missing', "{$p}.label", 'label рекомендуется для UI');
                }

                $valueType = $field['value_type'] ?? null;
                if ($valueType !== null) {
                    $vt = (string)$valueType;
                    if ($vt !== '' && !in_array($vt, ['string','int','float','decimal','bool'], true)) {
                        $out[] = ValidationError::warning(
                            'inputs.field.value_type.unknown',
                            "{$p}.value_type",
                            'Неизвестный value_type',
                            ['value_type' => $vt]
                        );
                    }
                }

                // select should have options or options_api_method or options_file
                if (($field['type'] ?? null) === 'select') {
                    $hasOptions = isset($field['options']) && is_array($field['options']) && $field['options'] !== [];
                    $hasMethod  = isset($field['options_api_method']) && is_string($field['options_api_method']) && $field['options_api_method'] !== '';
                    $hasFile    = isset($field['options_file']) && is_string($field['options_file']) && $field['options_file'] !== '';

                    if (!$hasOptions && !$hasMethod && !$hasFile) {
                        $out[] = ValidationError::warning(
                            'inputs.select.no_options',
                            $p,
                            'select поле без options/options_api_method/options_file'
                        );
                    }
                }
            }

            // options_fields (optional)
            $opt = $inputs[$group]['options_fields'] ?? [];
            if ($opt !== null && !is_array($opt)) {
                $out[] = ValidationError::error("inputs.{$group}.options_fields.type", "inputs.{$group}.options_fields", "options_fields должен быть массивом");
            }
        }

        return $out;
    }
}
