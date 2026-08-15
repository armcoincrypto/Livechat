<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation\Rules;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class OperationsRules
{
    private const ALLOWED_TYPES = ['incoming', 'outgoing', 'service'];

    /** @return ValidationError[] */
    public static function check(GatewayConfig $cfg, bool $strict): array
    {
        $out = [];
        $all = $cfg->all();

        $ops = $all['operations'] ?? null;
        if (!is_array($ops) || $ops === []) {
            $out[] = ValidationError::error('operations.missing', 'operations', 'operations обязателен и должен быть непустым массивом');
            return $out;
        }

        foreach ($ops as $opKey => $opCfg) {
            $path = "operations.{$opKey}";

            if (!is_string($opKey) || trim($opKey) === '') {
                $out[] = ValidationError::error('operations.key.invalid', $path, 'Ключ операции должен быть строкой');
                continue;
            }

            if (!is_array($opCfg)) {
                $out[] = ValidationError::error('operations.item.type', $path, 'Операция должна быть массивом');
                continue;
            }

            $type = (string)($opCfg['type'] ?? '');
            if ($type === '') {
                $out[] = ValidationError::error('operations.type.missing', "{$path}.type", 'type обязателен (incoming|outgoing|service)');
            } elseif (!in_array($type, self::ALLOWED_TYPES, true)) {
                $out[] = ValidationError::error(
                    'operations.type.invalid',
                    "{$path}.type",
                    'Недопустимый type операции',
                    ['type' => $type, 'allowed' => self::ALLOWED_TYPES]
                );
            }

            $reqClass = $opCfg['request_class'] ?? null;
            if (!is_string($reqClass) || $reqClass === '') {
                $out[] = ValidationError::error('operations.request_class.missing', "{$path}.request_class", 'request_class обязателен');
            } elseif (!class_exists($reqClass)) {
                $out[] = ValidationError::error('operations.request_class.not_found', "{$path}.request_class", 'request_class не найден', ['class' => $reqClass]);
            }

            $resClass = $opCfg['response_class'] ?? null;
            if (!is_string($resClass) || $resClass === '') {
                $out[] = ValidationError::error('operations.response_class.missing', "{$path}.response_class", 'response_class обязателен');
            } elseif (!class_exists($resClass)) {
                $out[] = ValidationError::error('operations.response_class.not_found', "{$path}.response_class", 'response_class не найден', ['class' => $resClass]);
            }

            // human label recommended
            $label = (string)($opCfg['label'] ?? '');
            if ($label === '') {
                $out[] = ValidationError::warning('operations.label.missing', "{$path}.label", 'label рекомендуется для UI/админки');
            }
        }

        return $out;
    }
}
