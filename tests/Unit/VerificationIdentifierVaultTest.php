<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Verification\VerificationIdentifierVault;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;

/**
 * Pure vault crypto/normalize coverage. DB coverage certification lives in
 * scripts/test/certification/run_verification_encrypted_primary_read.php.
 */
final class VerificationIdentifierVaultTest extends TestCase
{
    private function makeKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    public function test_normalize_last4_encrypt_round_trip_and_pack(): void
    {
        $cardKey = $this->makeKey();
        $lookupKey = $this->makeKey();
        $lookupKeyAlt = $this->makeKey();

        Config::set('verification.card_key', $cardKey);
        Config::set('verification.lookup_key', $lookupKey);
        Config::set('verification.encrypted_write_enabled', true);
        Config::set('verification.legacy_plaintext_write_enabled', false);
        Config::set('verification.plaintext_fallback_enabled', false);

        $vault = new VerificationIdentifierVault();

        $plain = '4111 1111 1111 1111';
        $norm = $vault->normalize($plain);
        $this->assertSame('4111111111111111', $norm);
        $this->assertSame('AB12CD', $vault->normalize('AB 12 CD'));
        $this->assertSame('1111', $vault->lastFour($norm));

        $ct = $vault->encrypt($norm);
        $this->assertStringStartsWith('v1:', $ct);
        $this->assertStringNotContainsString($norm, $ct);
        $this->assertSame($norm, $vault->decrypt($ct));

        $h1 = $vault->lookupHash($norm);
        $this->assertSame(64, strlen($h1));
        $this->assertSame($h1, $vault->lookupHash($norm));

        Config::set('verification.lookup_key', $lookupKeyAlt);
        $vaultAlt = new VerificationIdentifierVault();
        $this->assertNotSame($h1, $vaultAlt->lookupHash($norm));

        Config::set('verification.lookup_key', $lookupKey);
        $vault = new VerificationIdentifierVault();

        $pack = $vault->packForStorage('4111 1111 1111 9999', '4111 1111 1111 9999');
        $this->assertArrayNotHasKey('card_number', $pack);
        $this->assertArrayNotHasKey('card_number_string', $pack);
        $this->assertStringStartsWith('v1:', (string) $pack['card_number_ciphertext']);
        $this->assertSame(64, strlen((string) $pack['card_number_lookup']));
        $this->assertSame('9999', $pack['card_number_last4']);

        $this->assertSame(
            '4111111111119999',
            $vault->resolve($pack['card_number_ciphertext'], '0000')
        );
        $this->assertNull($vault->resolve(null, '12345678'));
    }

    public function test_plaintext_fallback_when_enabled(): void
    {
        Config::set('verification.card_key', $this->makeKey());
        Config::set('verification.lookup_key', $this->makeKey());
        Config::set('verification.plaintext_fallback_enabled', true);

        $vault = new VerificationIdentifierVault();
        $vault->resetResolvePathCounts();
        $this->assertSame('4111111111111111', $vault->resolve(null, '4111111111111111'));
        $counts = $vault->resolvePathCounts();
        $this->assertSame(1, (int) ($counts['plaintext_fallback'] ?? 0));
    }
}
