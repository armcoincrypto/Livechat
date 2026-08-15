<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation\Rules;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class CapabilitiesRules
{
    /** @return ValidationError[] */
    public static function check(GatewayConfig $cfg, bool $strict): array
    {
        $out = [];

        $all = $cfg->all();
        $caps = $cfg->capabilities();

        if (!is_array($caps) || $caps === []) {
            $out[] = ValidationError::error('capabilities.missing', 'capabilities', 'capabilities обязателен и должен быть массивом');
            return $out;
        }

        $incoming = (bool)($caps['incoming'] ?? false);
        $outgoing = (bool)($caps['outgoing'] ?? false);

        if (!$incoming && !$outgoing) {
            $out[] = ValidationError::warning(
                'capabilities.neither',
                'capabilities.incoming/outgoing',
                'Оба флага incoming и outgoing выключены — шлюз не используется',
            );
        }

        // auto_operations
        $auto = (bool)($all['capabilities']['features']['auto_operations'] ?? false);
        if ($auto && empty($all['operations'])) {
            $out[] = ValidationError::error(
                'capabilities.auto_operations.no_operations',
                'capabilities.features.auto_operations',
                'auto_operations=true, но operations пустой'
            );
        }

        // callbacks block must be array if present
        $callbacks = $all['capabilities']['callbacks'] ?? null;
        if ($callbacks !== null && !is_array($callbacks)) {
            $out[] = ValidationError::error(
                'capabilities.callbacks.type',
                'capabilities.callbacks',
                'capabilities.callbacks должен быть массивом'
            );
        }

        return $out;
    }
}
