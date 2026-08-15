<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Validation\Rules;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Validation\ValidationError;

final class CallbackRules
{
    /** @return ValidationError[] */
    public static function check(GatewayConfig $cfg, bool $strict): array
    {
        $out = [];
        $all = $cfg->all();

        // Если включен capabilities.callbacks.ipn -> желательно описать inputs.merchant.callback
        $capCallbacks = $all['capabilities']['callbacks'] ?? [];
        $ipnEnabled = is_array($capCallbacks) ? (bool)($capCallbacks['ipn'] ?? false) : false;

        if (!$ipnEnabled) {
            return $out;
        }

        $merchantInputs = $all['inputs']['merchant'] ?? null;
        if (!is_array($merchantInputs)) {
            $out[] = ValidationError::warning(
                'callback.merchant_inputs.missing',
                'inputs.merchant',
                'callbacks.ipn=true, но inputs.merchant отсутствует'
            );
            return $out;
        }

        $cb = $merchantInputs['callback'] ?? null;
        if (!is_array($cb)) {
            $out[] = ValidationError::warning(
                'callback.block.missing',
                'inputs.merchant.callback',
                'callbacks.ipn=true, но inputs.merchant.callback не описан (route_name/order_id_field/ip_whitelist_enabled)'
            );
            return $out;
        }

        $enabled = (bool)($cb['enabled'] ?? false);
        if (!$enabled) {
            $out[] = ValidationError::warning('callback.disabled', 'inputs.merchant.callback.enabled', 'callbacks.ipn=true, но callback.enabled=false');
        }

        $route = (string)($cb['route_name'] ?? '');
        if ($enabled && $route === '') {
            $out[] = ValidationError::warning('callback.route_name.missing', 'inputs.merchant.callback.route_name', 'callback.enabled=true, но route_name пустой');
        }

        $field = (string)($cb['order_id_field'] ?? '');
        if ($enabled && $field === '') {
            $out[] = ValidationError::warning('callback.order_id_field.missing', 'inputs.merchant.callback.order_id_field', 'callback.enabled=true, но order_id_field пустой');
        }

        return $out;
    }
}
