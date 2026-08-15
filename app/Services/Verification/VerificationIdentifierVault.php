<?php

declare(strict_types=1);

namespace App\Services\Verification;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Canonical encrypt/decrypt/normalize/lookup for verification_card identifiers.
 * Do not call Crypt directly from controllers for these fields.
 */
final class VerificationIdentifierVault
{
    public const ENVELOPE_PREFIX = 'v1:';

    public const KEY_VERSION = 1;

    private ?Encrypter $encrypter = null;

    public function normalize(?string $value): string
    {
        return preg_replace('/\s+/u', '', (string) $value) ?? '';
    }

    public function lastFour(string $normalized): string
    {
        $len = mb_strlen($normalized);
        if ($len === 0) {
            return '';
        }

        return mb_substr($normalized, -min(4, $len));
    }

    public function isEncryptedWriteEnabled(): bool
    {
        return (bool) config('verification.encrypted_write_enabled', false);
    }

    public function isLegacyPlaintextWriteEnabled(): bool
    {
        return (bool) config('verification.legacy_plaintext_write_enabled', false);
    }

    public function isPlaintextFallbackEnabled(): bool
    {
        return (bool) config('verification.plaintext_fallback_enabled', true);
    }

    public function encrypt(string $plain): string
    {
        $this->assertEncryptionKeysReady();

        return self::ENVELOPE_PREFIX.$this->encrypter()->encryptString($plain);
    }

    /**
     * @throws RuntimeException|DecryptException
     */
    public function decrypt(string $envelope): string
    {
        $this->assertEncryptionKeysReady();

        if (! str_starts_with($envelope, self::ENVELOPE_PREFIX)) {
            throw new RuntimeException('verification_identifier_unknown_version');
        }

        $ciphertext = substr($envelope, strlen(self::ENVELOPE_PREFIX));
        if ($ciphertext === '') {
            throw new RuntimeException('verification_identifier_empty_ciphertext');
        }

        return $this->encrypter()->decryptString($ciphertext);
    }

    public function lookupHash(string $normalized): string
    {
        $key = $this->lookupKeyBytes();
        if ($key === '') {
            throw new RuntimeException('verification_identifier_lookup_key_missing');
        }

        return hash_hmac('sha256', $normalized, $key);
    }

    /**
     * Encrypted-only payload for VerificationCard create/update.
     * Legacy plaintext columns are retired — never included.
     *
     * @return array{
     *   card_number_ciphertext: ?string,
     *   card_number_string_ciphertext: ?string,
     *   card_number_lookup: ?string,
     *   card_number_last4: ?string,
     *   identifier_key_version: int
     * }
     */
    public function packForStorage(string $cardNumber, ?string $cardNumberString = null): array
    {
        if ($this->isLegacyPlaintextWriteEnabled()) {
            throw new RuntimeException('verification_identifier_plaintext_columns_retired');
        }

        $normalized = $this->normalize($cardNumber);
        $display = $cardNumberString !== null ? (string) $cardNumberString : $normalized;

        if (! $this->isEncryptedWriteEnabled()) {
            throw new RuntimeException('verification_identifier_encrypted_write_required');
        }

        if ($normalized === '') {
            throw new RuntimeException('verification_identifier_empty_for_encrypted_write');
        }

        $this->assertEncryptionKeysReady();
        $this->assertLookupKeyReady();

        return [
            'card_number_ciphertext' => $this->encrypt($normalized),
            'card_number_string_ciphertext' => $this->encrypt($display),
            'card_number_lookup' => $this->lookupHash($normalized),
            'card_number_last4' => $this->lastFour($normalized),
            'identifier_key_version' => self::KEY_VERSION,
        ];
    }

    /**
     * Ciphertext-first resolve. Returns null when unavailable.
     * Never returns ciphertext. Does not log plaintext.
     */
    public function resolve(?string $ciphertext, ?string $legacyPlain): ?string
    {
        $ciphertext = is_string($ciphertext) ? trim($ciphertext) : '';
        if ($ciphertext !== '') {
            try {
                $plain = $this->decrypt($ciphertext);
                $this->recordResolvePath('ciphertext');

                return $plain;
            } catch (Throwable) {
                $this->recordResolvePath('decrypt_fail');

                return null;
            }
        }

        if (! $this->isPlaintextFallbackEnabled()) {
            $this->recordResolvePath('unavailable');

            return null;
        }

        $legacy = $this->normalize($legacyPlain);
        if ($legacy === '') {
            $this->recordResolvePath('unavailable');

            return null;
        }

        $this->recordResolvePath('plaintext_fallback');

        return $legacy;
    }

    /**
     * Counters only (no identifiers). Keys:
     * verification_id_resolve_{ciphertext|plaintext_fallback|decrypt_fail|unavailable}
     *
     * @return array<string, int>
     */
    public function resolvePathCounts(): array
    {
        $keys = ['ciphertext', 'plaintext_fallback', 'decrypt_fail', 'unavailable'];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = (int) \Illuminate\Support\Facades\Cache::get('verification_id_resolve_'.$key, 0);
        }

        return $out;
    }

    public function resetResolvePathCounts(): void
    {
        foreach (['ciphertext', 'plaintext_fallback', 'decrypt_fail', 'unavailable'] as $key) {
            \Illuminate\Support\Facades\Cache::forget('verification_id_resolve_'.$key);
        }
    }

    private function recordResolvePath(string $path): void
    {
        try {
            \Illuminate\Support\Facades\Cache::increment('verification_id_resolve_'.$path);
        } catch (Throwable) {
            // Metrics must never break reads.
        }
    }

    public function assertEncryptionKeysReady(): void
    {
        if ($this->encrypter !== null) {
            return;
        }

        $raw = $this->decodeKey((string) config('verification.card_key'));
        if ($raw === null) {
            throw new RuntimeException('verification_identifier_encryption_key_invalid');
        }

        $cipher = (string) config('app.cipher', 'AES-256-CBC');
        if (! Encrypter::supported($raw, $cipher)) {
            throw new RuntimeException('verification_identifier_encryption_key_unsupported');
        }

        $this->encrypter = new Encrypter($raw, $cipher);
    }

    public function assertLookupKeyReady(): void
    {
        if ($this->lookupKeyBytes() === '') {
            throw new RuntimeException('verification_identifier_lookup_key_missing');
        }
    }

    private function encrypter(): Encrypter
    {
        $this->assertEncryptionKeysReady();

        return $this->encrypter;
    }

    private function lookupKeyBytes(): string
    {
        $configured = (string) config('verification.lookup_key');
        if ($configured === '') {
            return '';
        }

        $decoded = $this->decodeKey($configured);
        if ($decoded !== null) {
            return $decoded;
        }

        // Allow non-base64 secrets for HMAC (still never logged).
        return $configured;
    }

    private function decodeKey(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), true);
            if ($decoded === false || $decoded === '') {
                return null;
            }

            return $decoded;
        }

        // Raw 32-byte binary key not expected in env; require base64: for AES keys.
        if (strlen($value) === 32) {
            return $value;
        }

        return null;
    }
}
