<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Traits;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Payments;
use Throwable;

trait CallbackConfigTrait
{
    private function tryGetGatewayConfig(string $alias): ?GatewayConfig
    {
        try {
            return Payments::forConfig($alias);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function getMerchantCallbackConfig(GatewayConfig $config): array
    {
        $group = $config->inputsGroup('merchant');
        $cb = $group['callback'] ?? [];
        return is_array($cb) ? $cb : [];
    }

    private function supportsCompletePurchase(GatewayConfig $config): bool
    {
        $op = $config->operationConfig('complete_purchase');
        return is_array($op) && !empty($op['request_class']);
    }
}
