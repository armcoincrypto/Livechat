<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Services\Auth\OAuth\OAuthProviders;
use PHPUnit\Framework\TestCase;

final class OAuthProvidersTest extends TestCase
{
    public function test_supported_providers(): void
    {
        $this->assertTrue(OAuthProviders::isSupported('google'));
        $this->assertTrue(OAuthProviders::isSupported('Yandex'));
        $this->assertFalse(OAuthProviders::isSupported('vkontakte'));
        $this->assertSame('google', OAuthProviders::normalize(' Google '));
    }
}
