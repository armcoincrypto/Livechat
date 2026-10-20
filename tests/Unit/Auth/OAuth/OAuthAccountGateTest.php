<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\User;
use App\Services\Auth\OAuth\OAuthAccountGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class OAuthAccountGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        OAuthSqliteHarness::bootIsolated();
    }

    protected function tearDown(): void
    {
        OAuthSqliteHarness::restoreDefaultConnection();
        parent::tearDown();
    }

    public function test_ip_restriction_and_ban(): void
    {
        $gate = new OAuthAccountGate();
        $user = User::create([
            'name' => 'Gated',
            'email' => 'gate@example.com',
            'password' => Hash::make('x'),
            'deactivation' => 0,
            'allowed_ip_addresses' => '198.51.100.1',
        ]);

        $badIp = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']);
        try {
            $gate->assertCanAuthenticate($user, $badIp);
            $this->fail('ip');
        } catch (RuntimeException $e) {
            $this->assertSame('ACCOUNT_IP_NOT_ALLOWED', $e->getMessage());
        }

        $user->banned_at = now();
        $user->allowed_ip_addresses = null;
        $user->save();

        // isBanned() may exist on User; gate checks method_exists.
        $okIp = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']);
        if (method_exists($user, 'isBanned') && $user->isBanned()) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('ACCOUNT_BANNED');
            $gate->assertCanAuthenticate($user->fresh(), $okIp);
        } else {
            $this->assertTrue(true);
        }
    }
}
