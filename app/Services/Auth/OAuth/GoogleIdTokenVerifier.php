<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifies Google OIDC ID tokens using Google JWKS (RS256).
 * Does not log token contents.
 */
final class GoogleIdTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com',
    ];

    /**
     * @return array<string, mixed>
     */
    public function verify(string $idToken, string $audience, ?string $expectedNonce = null): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_MALFORMED');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $header = json_decode($this->b64urlDecode($headerB64), true);
        $payload = json_decode($this->b64urlDecode($payloadB64), true);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_MALFORMED');
        }

        if (($header['alg'] ?? '') !== 'RS256') {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_ALG');
        }

        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '') {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_KID');
        }

        $pem = $this->pemForKid($kid);
        $signed = $headerB64.'.'.$payloadB64;
        $signature = $this->b64urlDecode($sigB64);

        $ok = openssl_verify($signed, $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_SIGNATURE');
        }

        $iss = (string) ($payload['iss'] ?? '');
        if (!in_array($iss, self::ISSUERS, true)) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_ISS');
        }

        $aud = $payload['aud'] ?? null;
        $audOk = is_string($aud)
            ? hash_equals($aud, $audience)
            : (is_array($aud) && in_array($audience, $aud, true));
        if (!$audOk) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_AUD');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < time()) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_EXP');
        }

        if ($expectedNonce !== null && $expectedNonce !== '') {
            $nonce = (string) ($payload['nonce'] ?? '');
            if ($nonce === '' || !hash_equals($nonce, $expectedNonce)) {
                throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_NONCE');
            }
        }

        $sub = trim((string) ($payload['sub'] ?? ''));
        if ($sub === '') {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_SUB');
        }

        return $payload;
    }

    private function pemForKid(string $kid): string
    {
        $jwks = Cache::remember('exs_google_jwks_v3', 3600, function () {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get(self::JWKS_URL);

            if (!$response->successful()) {
                throw new RuntimeException('OAUTH_GOOGLE_JWKS_FETCH');
            }

            $json = $response->json();
            if (!is_array($json) || !isset($json['keys']) || !is_array($json['keys'])) {
                throw new RuntimeException('OAUTH_GOOGLE_JWKS_INVALID');
            }

            return $json;
        });

        foreach ($jwks['keys'] as $key) {
            if (!is_array($key) || ($key['kid'] ?? '') !== $kid) {
                continue;
            }
            if (($key['kty'] ?? '') !== 'RSA') {
                continue;
            }

            return $this->jwkToPem((string) $key['n'], (string) $key['e']);
        }

        Cache::forget('exs_google_jwks_v3');
        throw new RuntimeException('OAUTH_GOOGLE_JWKS_KID');
    }

    private function jwkToPem(string $n, string $e): string
    {
        $modulus = $this->b64urlDecode($n);
        $exponent = $this->b64urlDecode($e);

        $components = [
            $this->asn1Integer($modulus),
            $this->asn1Integer($exponent),
        ];
        $sequence = $this->asn1Sequence(implode('', $components));
        $bitString = "\x03".$this->asn1Length(strlen($sequence) + 1)."\x00".$sequence;

        $rsaOid = hex2bin('300d06092a864886f70d0101010500');
        $publicKey = $this->asn1Sequence($rsaOid.$bitString);
        $pem = '-----BEGIN PUBLIC KEY-----'.PHP_EOL
            .chunk_split(base64_encode($publicKey), 64, PHP_EOL)
            .'-----END PUBLIC KEY-----';

        return $pem;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($temp)).$temp;
    }

    private function asn1Integer(string $bytes): string
    {
        if (ord($bytes[0]) > 0x7f) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->asn1Length(strlen($bytes)).$bytes;
    }

    private function asn1Sequence(string $contents): string
    {
        return "\x30".$this->asn1Length(strlen($contents)).$contents;
    }

    private function b64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('OAUTH_GOOGLE_B64');
        }

        return $decoded;
    }
}
