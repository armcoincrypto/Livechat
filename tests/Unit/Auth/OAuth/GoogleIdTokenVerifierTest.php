<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Services\Auth\OAuth\GoogleIdTokenVerifier;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

final class GoogleIdTokenVerifierTest extends TestCase
{
    private string $privatePem = '';
    private string $kid = 'test-kid-1';

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Config::set('cache.default', 'array');
        Cache::setDefaultDriver('array');

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $pem = '';
        $this->assertTrue(openssl_pkey_export($key, $pem));
        $this->privatePem = $pem;
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);

        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        Cache::put('exs_google_jwks_v3', [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $this->kid,
                'alg' => 'RS256',
                'n' => $n,
                'e' => $e,
            ]],
        ], 3600);
    }

    protected function tearDown(): void
    {
        Cache::forget('exs_google_jwks_v3');
        parent::tearDown();
    }

    public function test_valid_token_passes(): void
    {
        $token = $this->mint([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'exp' => time() + 300,
            'sub' => 'google-sub-1',
            'email' => 'a@example.com',
            'email_verified' => true,
            'nonce' => 'n-1',
        ]);

        $claims = (new GoogleIdTokenVerifier())->verify($token, 'client-123', 'n-1');
        $this->assertSame('google-sub-1', $claims['sub']);
    }

    public function test_bad_signature(): void
    {
        $token = $this->mint([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'exp' => time() + 300,
            'sub' => 'x',
        ]);
        $parts = explode('.', $token);
        $parts[2] = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $bad = implode('.', $parts);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_GOOGLE_ID_TOKEN_SIGNATURE');
        (new GoogleIdTokenVerifier())->verify($bad, 'client-123');
    }

    public function test_bad_audience(): void
    {
        $token = $this->mint([
            'iss' => 'https://accounts.google.com',
            'aud' => 'other-client',
            'exp' => time() + 300,
            'sub' => 'x',
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_GOOGLE_ID_TOKEN_AUD');
        (new GoogleIdTokenVerifier())->verify($token, 'client-123');
    }

    public function test_bad_issuer(): void
    {
        $token = $this->mint([
            'iss' => 'https://evil.example',
            'aud' => 'client-123',
            'exp' => time() + 300,
            'sub' => 'x',
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_GOOGLE_ID_TOKEN_ISS');
        (new GoogleIdTokenVerifier())->verify($token, 'client-123');
    }

    public function test_expired(): void
    {
        $token = $this->mint([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'exp' => time() - 10,
            'sub' => 'x',
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_GOOGLE_ID_TOKEN_EXP');
        (new GoogleIdTokenVerifier())->verify($token, 'client-123');
    }

    public function test_bad_nonce(): void
    {
        $token = $this->mint([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'exp' => time() + 300,
            'sub' => 'x',
            'nonce' => 'expected',
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_GOOGLE_ID_TOKEN_NONCE');
        (new GoogleIdTokenVerifier())->verify($token, 'client-123', 'other');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mint(array $payload): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $h = $this->b64(json_encode($header, JSON_THROW_ON_ERROR));
        $p = $this->b64(json_encode($payload, JSON_THROW_ON_ERROR));
        $signed = $h.'.'.$p;
        openssl_sign($signed, $sig, $this->privatePem, OPENSSL_ALGO_SHA256);

        return $signed.'.'.$this->b64($sig);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
