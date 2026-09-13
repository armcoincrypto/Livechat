<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Kobbopay;

use App\Gateways\Crypto\Kobbopay\KobbopayMerchantApiPath;
use App\Models\GatewayMerchant;
use iEXPackages\Payments\Payments;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class KobbopayHealthOptionsPathTest extends TestCase
{
    public function test_token_fetch_path_is_api_prefixed_and_does_not_double_api(): void
    {
        $this->assertSame('/api/user/token/fetch', KobbopayMerchantApiPath::TOKEN_FETCH);
        $this->assertSame(
            '/api/user/token/fetch',
            KobbopayMerchantApiPath::tokenFetch('https://merchant.kobbex.com')
        );
        $this->assertSame(
            '/user/token/fetch',
            KobbopayMerchantApiPath::tokenFetch('https://merchant.kobbex.com/api')
        );
        $this->assertStringNotContainsString(
            '/api/api/',
            KobbopayMerchantApiPath::tokenFetch('https://merchant.kobbex.com')
        );
        $this->assertStringNotContainsString(
            '/api/api/',
            KobbopayMerchantApiPath::tokenFetch('https://merchant.kobbex.com/api')
        );
    }

    public function test_health_and_options_source_no_longer_call_spa_token_fetch(): void
    {
        $health = (string) file_get_contents(app_path('Gateways/Crypto/Kobbopay/Messages/HealthRequest.php'));
        $options = (string) file_get_contents(app_path('Gateways/Crypto/Kobbopay/Messages/OptionsRequest.php'));
        $abstract = (string) file_get_contents(app_path('Gateways/Crypto/Kobbopay/Messages/AbstractRequest.php'));

        $this->assertStringContainsString('tokenCatalogPath()', $health);
        $this->assertStringContainsString('tokenCatalogPath()', $options);
        $this->assertStringContainsString('KobbopayMerchantApiPath::tokenFetch', $abstract);
        $this->assertStringNotContainsString("'/user/token/fetch'", $health);
        $this->assertStringNotContainsString("'/user/token/fetch'", $options);
        $this->assertStringContainsString(
            "'/api/crypto/invoice/create'",
            (string) file_get_contents(app_path('Gateways/Crypto/Kobbopay/Messages/PurchaseRequest.php'))
        );
    }

    public function test_authenticated_health_and_options_hit_api_token_fetch(): void
    {
        $merchant = GatewayMerchant::query()->where('alias', 'kobbopay')->where('status', 1)->first();
        if ($merchant === null) {
            $this->markTestSkipped('No active kobbopay merchant in this environment');
        }

        $seen = [];
        Http::fake(function (HttpRequest $request) use (&$seen) {
            $seen[] = [
                'method' => strtoupper($request->method()),
                'url' => $request->url(),
                'has_api_public' => $request->hasHeader('ApiPublic'),
                'has_signature' => $request->hasHeader('Signature'),
            ];

            return Http::response(['tokens' => ['USDTPOLYGON', 'USDTTRC']], 200);
        });

        $health = Payments::forMerchant($merchant)->run('health', []);
        $this->assertSame('ok', $health->getHealthStatus());

        $options = Payments::forMerchant($merchant)->run('options', [
            'method' => 'getCurrencies',
        ]);

        $this->assertNotEmpty($seen);
        foreach ($seen as $call) {
            $this->assertSame('GET', $call['method']);
            $this->assertStringContainsString('/api/user/token/fetch', $call['url']);
            $this->assertStringNotContainsString('/api/api/', $call['url']);
            $this->assertDoesNotMatchRegularExpression('#https://[^/]+/user/token/fetch$#', $call['url']);
            $this->assertTrue($call['has_api_public']);
            $this->assertTrue($call['has_signature']);
        }

        $optionsData = $options->getData();
        $this->assertArrayHasKey('USDTPOLYGON', $optionsData);
    }
}
