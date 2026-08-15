<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Services;

use App\Models\GatewayMerchant;
use iEXPackages\Payments\Payments;
use Throwable;

/**
 * Resolves the Kobbopay inbound webhook signing secret.
 *
 * Order:
 * 1) Merchant vault field webhook_secret (via gateway inputs)
 * 2) Env KOBBOPAY_WEBHOOK_SECRET
 *
 * Never logs the secret value.
 */
final class KobbopayWebhookSecretResolver
{
    public function resolve(?GatewayMerchant $merchant = null): ?string
    {
        $fromVault = $this->fromMerchantVault($merchant);
        if (is_string($fromVault) && $fromVault !== '') {
            return $fromVault;
        }

        $fromEnv = trim((string) env('KOBBOPAY_WEBHOOK_SECRET', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return null;
    }

    private function fromMerchantVault(?GatewayMerchant $merchant): ?string
    {
        try {
            $merchant ??= GatewayMerchant::query()->where('alias', 'kobbopay')->first();
            if (!$merchant) {
                return null;
            }

            $gateway = Payments::forMerchant($merchant);
            $request = $gateway->request('health', [])->withMerchant($merchant);

            if (method_exists($request, 'getWebhookSecret')) {
                $value = $request->getWebhookSecret();
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
