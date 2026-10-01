<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Verification\VerificationIdentifierVault;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;

/**
 * Synthetic ciphertext-first resolve coverage.
 * Full DB coverage certification:
 * scripts/test/certification/run_verification_encrypted_primary_read.php
 */
final class VerificationEncryptedPrimaryReadTest extends TestCase
{
    public function test_ciphertext_first_resolve_ignores_wrong_legacy_hint(): void
    {
        Config::set('verification.card_key', 'base64:'.base64_encode(random_bytes(32)));
        Config::set('verification.lookup_key', 'base64:'.base64_encode(random_bytes(32)));
        Config::set('verification.encrypted_write_enabled', true);
        Config::set('verification.plaintext_fallback_enabled', true);

        $vault = new VerificationIdentifierVault();
        $vault->resetResolvePathCounts();

        $norm = $vault->normalize('4111 1111 1111 1111');
        $ct = $vault->encrypt($norm);

        $resolved = $vault->resolve($ct, '0000WRONGLEGACY');
        $this->assertSame($norm, $vault->normalize((string) $resolved));

        $counts = $vault->resolvePathCounts();
        $this->assertGreaterThanOrEqual(1, (int) ($counts['ciphertext'] ?? 0));
        $this->assertSame(0, (int) ($counts['plaintext_fallback'] ?? 0));
    }

    public function test_fallback_metric_increments_when_no_ciphertext(): void
    {
        Config::set('verification.card_key', 'base64:'.base64_encode(random_bytes(32)));
        Config::set('verification.lookup_key', 'base64:'.base64_encode(random_bytes(32)));
        Config::set('verification.plaintext_fallback_enabled', true);

        $vault = new VerificationIdentifierVault();
        $vault->resetResolvePathCounts();
        $this->assertSame('4111111111111111', $vault->resolve(null, '4111111111111111'));
        $counts = $vault->resolvePathCounts();
        $this->assertSame(1, (int) ($counts['plaintext_fallback'] ?? 0));
    }
}
