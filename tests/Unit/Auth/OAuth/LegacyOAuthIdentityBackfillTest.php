<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\User;
use App\Models\UserOAuthIdentity;
use App\Services\Auth\OAuth\LegacyOAuthIdentityBackfill;
use App\Services\Auth\OAuth\OAuthProviders;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LegacyOAuthIdentityBackfillTest extends TestCase
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

    public function test_dry_run_and_apply_are_idempotent(): void
    {
        User::create([
            'name' => 'Legacy',
            'email' => 'lg@example.com',
            'password' => Hash::make('x'),
            'provider' => OAuthProviders::GOOGLE,
            'provider_id' => 'g-bf-1',
            'deactivation' => 1,
        ]);

        $svc = new LegacyOAuthIdentityBackfill();
        $dry = $svc->run(true, 'google');
        $this->assertFalse($dry['aborted']);
        $this->assertSame(1, $dry['would_insert']);
        $this->assertSame(0, $dry['inserted']);
        $this->assertSame(0, UserOAuthIdentity::query()->count());

        $apply = $svc->run(false, 'google');
        $this->assertSame(1, $apply['inserted']);
        $this->assertSame(1, UserOAuthIdentity::query()->count());

        $again = $svc->run(false, 'google');
        $this->assertSame(0, $again['inserted']);
        $this->assertSame(1, $again['skipped_existing']);
        $this->assertSame(1, UserOAuthIdentity::query()->count());

        // Deactivation untouched.
        $this->assertSame(1, (int) User::query()->where('email', 'lg@example.com')->value('deactivation'));
    }

    public function test_conflict_aborts_without_writes(): void
    {
        User::create([
            'name' => 'A',
            'email' => 'a1@example.com',
            'password' => Hash::make('x'),
            'provider' => OAuthProviders::GOOGLE,
            'provider_id' => 'dup-sub',
        ]);
        User::create([
            'name' => 'B',
            'email' => 'b1@example.com',
            'password' => Hash::make('x'),
            'provider' => OAuthProviders::GOOGLE,
            'provider_id' => 'dup-sub',
        ]);

        $report = (new LegacyOAuthIdentityBackfill())->run(false, 'google');
        $this->assertTrue($report['aborted']);
        $this->assertSame(0, UserOAuthIdentity::query()->count());
        $this->assertNotEmpty($report['conflicts']);
    }
}
