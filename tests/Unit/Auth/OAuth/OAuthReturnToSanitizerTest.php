<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Services\Auth\OAuth\OAuthReturnToSanitizer;
use PHPUnit\Framework\TestCase;

final class OAuthReturnToSanitizerTest extends TestCase
{
    public function test_accepts_locale_relative_paths(): void
    {
        $this->assertSame('/en/account', OAuthReturnToSanitizer::sanitize('/en/account'));
        $this->assertSame('/ru/account/settings', OAuthReturnToSanitizer::sanitize('/ru/account/settings'));
    }

    public function test_rejects_external_and_auth_paths(): void
    {
        $this->assertNull(OAuthReturnToSanitizer::sanitize('https://evil.example/x'));
        $this->assertNull(OAuthReturnToSanitizer::sanitize('//evil.example/x'));
        $this->assertNull(OAuthReturnToSanitizer::sanitize('/en/auth/login'));
        $this->assertNull(OAuthReturnToSanitizer::sanitize('/account'));
        $this->assertNull(OAuthReturnToSanitizer::sanitize("javascript:alert(1)"));
        $this->assertNull(OAuthReturnToSanitizer::sanitize("/en/%2e%2e/auth/login"));
    }
}
