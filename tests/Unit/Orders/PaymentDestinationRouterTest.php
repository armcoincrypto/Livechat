<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\KobbopayDepositAddressValidator;
use App\Services\Orders\PaymentDestinationRouter;
use App\Services\Rates\ZelleUsdBenchmarkResolver;
use Tests\TestCase;

final class PaymentDestinationRouterTest extends TestCase
{
    public function test_production_usdt_letter_cods_map_to_kobbopay(): void
    {
        foreach (['USDTTRC20', 'USDTBEP20', 'USDTERC20', 'USDTPOLYGON'] as $xml) {
            $currency = Currency::query()->where('designation_xml', $xml)->first();
            $this->assertNotNull($currency, $xml);
            $this->assertSame(
                PaymentDestinationRouter::OWNER_KOBBOPAY,
                PaymentDestinationRouter::classifyCurrency($currency),
                $xml
            );
        }

        $this->assertSame('USDTTRC', PaymentDestinationRouter::KOBBOPAY_NETWORK_BY_LETTER_COD['USDTTRC20']);
        $this->assertSame('USDTBSC', PaymentDestinationRouter::KOBBOPAY_NETWORK_BY_LETTER_COD['USDTBEP20']);
        $this->assertSame('USDTERC', PaymentDestinationRouter::KOBBOPAY_NETWORK_BY_LETTER_COD['USDTERC20']);
        $this->assertSame('USDTPOLYGON', PaymentDestinationRouter::KOBBOPAY_NETWORK_BY_LETTER_COD['USDTPOLYGON']);
    }

    public function test_usdt_polygon_rail_is_live_kobbopay_only(): void
    {
        $currency = Currency::query()->where('designation_xml', 'USDTPOLYGON')->first();
        $this->assertNotNull($currency);
        $this->assertSame('USDTPOLYGON', strtoupper((string) $currency->network_code));
        $this->assertSame(
            'USDTPOLYGON',
            PaymentDestinationRouter::expectedKobbopayNetworkCode($currency)
        );
        $this->assertSame(0, (int) ($currency->visible_receiving ?? 0));
        $this->assertFalse(
            Currency::query()->where('designation_xml', 'USDTMATIC')->exists(),
            'USDTMATIC must not be invented; provider token is USDTPOLYGON'
        );
    }

    public function test_zelle_send_is_currency_id_87(): void
    {
        $currency = Currency::query()->find(ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID);
        $this->assertNotNull($currency);
        $this->assertSame('ZELLEUSD', strtoupper((string) $currency->designation_xml));
        $this->assertTrue(PaymentDestinationRouter::isZelleInboundCurrency($currency));
        $this->assertSame(
            PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION,
            PaymentDestinationRouter::classifyCurrency($currency)
        );
    }

    public function test_btc_uses_exswaping_requisite_owner(): void
    {
        $btc = Currency::query()->where('designation_xml', 'BTC')->first();
        $this->assertNotNull($btc);
        $this->assertSame(
            PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE,
            PaymentDestinationRouter::classifyCurrency($btc)
        );
    }

    public function test_kobbopay_rails_do_not_count_local_requisites_as_source(): void
    {
        $usdt = DirectionExchange::query()->find(15);
        $this->assertNotNull($usdt);
        $this->assertTrue(PaymentDestinationRouter::isKobbopayInbound($usdt));
        $this->assertTrue(PaymentDestinationRouter::hasActiveKobbopayMerchant($usdt));
        $this->assertTrue(InboundPaymentDestinationGuard::directionHasSource($usdt));

        $guardSrc = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/InboundPaymentDestinationGuard.php');
        $this->assertStringContainsString('OWNER_KOBBOPAY', $guardSrc);
        $this->assertStringContainsString('hasActiveKobbopayMerchant', $guardSrc);
    }

    public function test_kobbopay_address_validator_requires_matching_network_metadata(): void
    {
        $tron = 'TXYZabcdefghijklmnopqrstuvwxyz12345';
        $this->assertFalse(KobbopayDepositAddressValidator::isTronAddress($tron)); // wrong length alphabet maybe

        $validTron = 'TJRyWwFs9wTFGZg3VbrNpkC9smAvS4kK4Q';
        $evm = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $this->assertTrue(KobbopayDepositAddressValidator::isTronAddress($validTron));
        $this->assertTrue(KobbopayDepositAddressValidator::isEvmAddress($evm));

        $this->assertTrue(KobbopayDepositAddressValidator::accept($validTron, 'USDTTRC', 'USDTTRC'));
        $this->assertFalse(KobbopayDepositAddressValidator::accept($validTron, 'USDTTRC', ''));
        $this->assertFalse(KobbopayDepositAddressValidator::accept($evm, 'USDTTRC', 'USDTTRC'));
        $this->assertFalse(KobbopayDepositAddressValidator::accept($evm, 'USDTERC', 'USDTBSC'));
        $this->assertTrue(KobbopayDepositAddressValidator::accept($evm, 'USDTERC', 'USDTERC'));
        $this->assertTrue(KobbopayDepositAddressValidator::accept($evm, 'USDTBSC', 'USDTBSC'));
        // Polygon must not accept ERC/BSC provider tokens even with an EVM address.
        $this->assertTrue(KobbopayDepositAddressValidator::accept($evm, 'USDTPOLYGON', 'USDTPOLYGON'));
        $this->assertFalse(KobbopayDepositAddressValidator::accept($evm, 'USDTPOLYGON', 'USDTERC'));
        $this->assertFalse(KobbopayDepositAddressValidator::accept($evm, 'USDTPOLYGON', 'USDTBSC'));
        $this->assertFalse(KobbopayDepositAddressValidator::accept($evm, 'USDTPOLYGON', 'USDTTRC'));
    }

    public function test_zelle_gate_and_create_invariant_are_wired(): void
    {
        $validate = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Concerns/ValidatesOrderRules.php');
        $this->assertStringContainsString('VERIFICATION_REQUIRED', $validate);
        $this->assertStringContainsString('isZelleInbound', $validate);

        $manager = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php');
        $this->assertStringContainsString('abandonIfPaymentDestinationMissing', $manager);

        $identity = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Validation/Rules/IdentityVerificationRule.php');
        $this->assertStringContainsString('VERIFICATION_REQUIRED', $identity);

        $requisite = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Invoices/RequisiteManager.php');
        $this->assertStringContainsString('isKobbopayInboundCurrency', $requisite);
    }

    public function test_process_api_exposes_routing_contract(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Resources/Orders/OrderProcessResource.php');
        $this->assertStringContainsString('payment_destination_ready', $src);
        $this->assertStringContainsString('verification_required', $src);
        $this->assertStringContainsString('verification_url', $src);
        $this->assertStringContainsString('payment_routing_owner', $src);
    }
}
