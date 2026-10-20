<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Services\Auth\OAuth\OAuthStateStore;
use PHPUnit\Framework\TestCase;

final class OAuthStateStoreTest extends TestCase
{
    public function test_pkce_s256_is_deterministic_and_url_safe(): void
    {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = OAuthStateStore::codeChallengeS256($verifier);

        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            $challenge
        );
        $this->assertStringNotContainsString('+', $challenge);
        $this->assertStringNotContainsString('/', $challenge);
        $this->assertStringNotContainsString('=', $challenge);
    }

    public function test_create_code_verifier_length(): void
    {
        $v = OAuthStateStore::createCodeVerifier();
        $this->assertGreaterThanOrEqual(43, strlen($v));
    }
}
