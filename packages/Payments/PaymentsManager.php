<?php

declare(strict_types=1);

namespace iEXPackages\Payments;

use App\Facades\Vault;
use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PaymentsManager
{
    public function __construct(
        private readonly GatewayManager $manager,
    ) {}

    /**
     * @var array<string, array> key: alias|filename → merchant config
     */
    private array $configs = [];

    /**
     * @var array<string, GatewayInterface>
     */
    private array $gateways = [];

    public function fromFile(string $alias, string $filename): GatewayInterface
    {
        $cacheKey = $alias . '|' . $filename;

        if (!isset($this->configs[$cacheKey])) {
            $rawConfig = Vault::decryptFromFile($filename, 'gateways');

            if (!is_array($rawConfig)) {
                throw new RuntimeException("Merchant config for [{$alias}] from [{$filename}] must be array.");
            }

            $this->configs[$cacheKey] = $rawConfig;

            Log::debug("[Payment] Loaded merchant config for alias [{$alias}] from [{$filename}]", [
                'keys' => array_keys($rawConfig),
            ]);
        }

        if (!isset($this->gateways[$cacheKey])) {
            $this->gateways[$cacheKey] = $this->manager->forAlias($alias, $this->configs[$cacheKey]);
        }


        return $this->gateways[$cacheKey];
    }

    public function forMerchant(GatewayMerchant $merchant): GatewayInterface
    {
        $gateway = $this->fromFile($merchant->alias, $merchant->filename);

        if (method_exists($gateway, 'withMerchant')) {
            $gateway->withMerchant($merchant);
        }

        return $gateway;
    }

    public function forPayment(GatewayPayment $payment): GatewayInterface
    {
        $gateway = $this->fromFile($payment->alias, $payment->filename);

        if (method_exists($gateway, 'withPayment')) {
            $gateway->withPayment($payment);
        }

        return $gateway;
    }

    /**
     * Получить только статический config.php (GatewayConfig) по alias,
     * без конфигурации мерчанта.
     */
    public function forConfig(string $alias): GatewayConfig
    {
        $gateway = $this->manager->forAlias($alias, []);

        return $gateway->gatewayConfig();
    }
}
