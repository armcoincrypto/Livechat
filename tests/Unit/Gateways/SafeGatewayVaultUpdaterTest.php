<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways;

use App\Facades\Vault;
use App\Models\GatewayMerchant;
use App\Services\Gateways\SafeGatewayVaultUpdater;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use iEXPackages\Payments\Payments;
use PHPUnit\Framework\TestCase;

final class SafeGatewayVaultUpdaterTest extends TestCase
{
    private string $filename;

    private array $fieldDefs;

    private SafeGatewayVaultUpdater $updater;

    /** @var list<string> */
    private array $hiddenKeys = ['private_key', 'public_key', 'webhook_secret'];

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate HTTP fakes between tests (previous Http::fake() otherwise leaks).
        Http::swap(new HttpFactory);

        $this->filename = 'test-safe-vault-' . bin2hex(random_bytes(6));
        $this->fieldDefs = Payments::forConfig('kobbopay')->fields('merchant');
        $this->updater = new SafeGatewayVaultUpdater();

        Vault::encryptToFile($this->filename, [
            'private_key' => str_repeat('a', 64),
            'public_key' => 'public-identifier-abcdefgh',
            'webhook_secret' => 'webhook-secret-value-36chars-ok!!',
            'api_base_url' => 'https://merchant.kobbex.com',
        ], 'gateways');
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/vault/gateways/' . $this->filename . '.dat');
        if (is_file($path)) {
            @unlink($path);
        }
        foreach (glob(storage_path('app/vault/.prewrite-backups/' . $this->filename . '-*.dat')) ?: [] as $bak) {
            @unlink($bak);
        }
        parent::tearDown();
    }

    private function merchant(): GatewayMerchant
    {
        $m = new GatewayMerchant();
        $m->id = 913001;
        $m->alias = 'kobbopay';
        $m->filename = $this->filename;

        return $m;
    }

    private function lengths(): array
    {
        $cfg = Vault::decryptFromFile($this->filename, 'gateways', false);

        return [
            'private_key' => strlen((string) ($cfg['private_key'] ?? '')),
            'public_key' => strlen((string) ($cfg['public_key'] ?? '')),
            'webhook_secret' => strlen((string) ($cfg['webhook_secret'] ?? '')),
            'api_base_url' => strlen((string) ($cfg['api_base_url'] ?? '')),
            'private_key_present' => trim((string) ($cfg['private_key'] ?? '')) !== '',
            'webhook_secret_present' => trim((string) ($cfg['webhook_secret'] ?? '')) !== '',
        ];
    }

    public function test_updating_webhook_secret_preserves_outbound_credentials(): void
    {
        $before = $this->lengths();
        $result = $this->updater->updateMerchantVault(
            $this->merchant(),
            ['webhook_secret' => 'new-webhook-secret-value-rotated!!'],
            $this->hiddenKeys,
            $this->fieldDefs,
            false,
        );
        $after = $this->lengths();

        $this->assertTrue($result['webhook_only']);
        $this->assertSame('changed', $result['summary']['webhook_secret']);
        $this->assertSame('unchanged', $result['summary']['private_key']);
        $this->assertSame('unchanged', $result['summary']['public_key']);
        $this->assertSame('unchanged', $result['summary']['api_base_url']);
        $this->assertSame($before['private_key'], $after['private_key']);
        $this->assertSame($before['public_key'], $after['public_key']);
        $this->assertSame($before['api_base_url'], $after['api_base_url']);
        $this->assertNotSame($before['webhook_secret'], $after['webhook_secret']);
    }

    public function test_updating_private_key_preserves_webhook_secret_with_confirm_and_probe(): void
    {
        Http::fake([
            'merchant.kobbex.com/*' => Http::response(['ok' => true], 200),
        ]);

        $before = $this->lengths();
        $result = $this->updater->updateMerchantVault(
            $this->merchant(),
            ['private_key' => str_repeat('b', 64)],
            $this->hiddenKeys,
            $this->fieldDefs,
            true,
        );
        $after = $this->lengths();

        $this->assertSame('changed', $result['summary']['private_key']);
        $this->assertSame('unchanged', $result['summary']['webhook_secret']);
        $this->assertSame($before['webhook_secret'], $after['webhook_secret']);
        $this->assertTrue($result['probed']);
        $this->assertFalse($result['rolled_back']);
    }

    public function test_blank_masked_field_does_not_overwrite(): void
    {
        $before = $this->lengths();
        $this->updater->updateMerchantVault(
            $this->merchant(),
            [
                'private_key' => '',
                'public_key' => '** Параметр заполнен **',
                'webhook_secret' => '   ',
                'api_base_url' => 'https://merchant.kobbex.com',
            ],
            $this->hiddenKeys,
            $this->fieldDefs,
            false,
        );
        $after = $this->lengths();

        $this->assertSame($before, $after);
    }

    public function test_omitted_field_remains_unchanged(): void
    {
        $before = $this->lengths();
        $this->updater->updateMerchantVault(
            $this->merchant(),
            ['api_base_url' => 'https://merchant.kobbex.com'],
            $this->hiddenKeys,
            $this->fieldDefs,
            false,
        );
        $after = $this->lengths();
        $this->assertSame($before['private_key'], $after['private_key']);
        $this->assertSame($before['webhook_secret'], $after['webhook_secret']);
    }

    public function test_short_private_key_rejected_without_write(): void
    {
        $before = $this->lengths();
        try {
            $this->updater->updateMerchantVault(
                $this->merchant(),
                ['private_key' => str_repeat('x', 36)],
                $this->hiddenKeys,
                $this->fieldDefs,
                true,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('connectionFields.private_key', $e->errors());
        }
        $this->assertSame($before, $this->lengths());
    }

    public function test_outbound_change_requires_confirmation(): void
    {
        try {
            $this->updater->updateMerchantVault(
                $this->merchant(),
                ['private_key' => str_repeat('c', 64)],
                $this->hiddenKeys,
                $this->fieldDefs,
                false,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirm_outbound_credential_change', $e->errors());
        }
        $this->assertSame(64, $this->lengths()['private_key']);
    }

    public function test_failed_postwrite_probe_triggers_rollback(): void
    {
        Http::fake(function () {
            return Http::response(['message' => 'Invalid signature'], 401);
        });

        $before = $this->lengths();
        try {
            $this->updater->updateMerchantVault(
                $this->merchant(),
                ['private_key' => str_repeat('d', 64)],
                $this->hiddenKeys,
                $this->fieldDefs,
                true,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('restored', strtolower(collect($e->errors())->flatten()->first() ?? ''));
        }
        $after = $this->lengths();
        $this->assertSame($before['private_key'], $after['private_key']);
        $this->assertSame($before['webhook_secret'], $after['webhook_secret']);
        Http::assertSentCount(1);
    }

    public function test_wrong_field_mapping_rejected_when_private_equals_webhook(): void
    {
        $dup = str_repeat('e', 64);
        Vault::encryptToFile($this->filename, [
            'private_key' => str_repeat('a', 64),
            'public_key' => 'public-identifier-abcdefgh',
            'webhook_secret' => $dup,
            'api_base_url' => 'https://merchant.kobbex.com',
        ], 'gateways');

        try {
            $this->updater->updateMerchantVault(
                $this->merchant(),
                ['private_key' => $dup],
                $this->hiddenKeys,
                $this->fieldDefs,
                true,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('connectionFields.private_key', $e->errors());
        }
    }
}
