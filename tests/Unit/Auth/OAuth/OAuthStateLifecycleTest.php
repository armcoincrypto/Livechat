<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Services\Auth\OAuth\OAuthStateStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Tests\TestCase;

final class OAuthStateLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Session::setDefaultDriver('array');
        Session::flush();
        Session::start();
    }

    public function test_consume_is_one_time(): void
    {
        $store = new OAuthStateStore();
        $state = $store->begin([
            'provider' => 'google',
            'intent' => 'login',
            'locale' => 'en',
            'return_to' => '/en/account',
            'link_user_id' => null,
            'code_verifier' => 'verifier-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'nonce' => 'nonce-1',
        ]);

        $tx = $store->consume('google', $state);
        $this->assertSame('google', $tx['provider']);
        $this->assertSame('nonce-1', $tx['nonce']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_STATE_MISSING');
        $store->consume('google', $state);
    }

    public function test_expired_state_rejected(): void
    {
        $store = new OAuthStateStore();
        $state = $store->begin([
            'provider' => 'yandex',
            'intent' => 'login',
            'locale' => 'ru',
            'return_to' => null,
            'link_user_id' => null,
            'code_verifier' => 'verifier-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'nonce' => 'nonce-2',
        ]);

        $tx = Session::get('exs_oauth_tx');
        $tx['expires_at'] = time() - 10;
        Session::put('exs_oauth_tx', $tx);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_STATE_EXPIRED');
        $store->consume('yandex', $state);
    }

    public function test_provider_and_state_mismatch(): void
    {
        $store = new OAuthStateStore();
        $state = $store->begin([
            'provider' => 'google',
            'intent' => 'login',
            'locale' => 'en',
            'return_to' => null,
            'link_user_id' => null,
            'code_verifier' => 'verifier-cccccccccccccccccccccccccccccccc',
            'nonce' => 'nonce-3',
        ]);

        try {
            $store->consume('yandex', $state);
            $this->fail('expected provider mismatch');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_STATE_PROVIDER_MISMATCH', $e->getMessage());
        }

        // first consume cleared session — recreate
        $state = $store->begin([
            'provider' => 'google',
            'intent' => 'login',
            'locale' => 'en',
            'return_to' => null,
            'link_user_id' => null,
            'code_verifier' => 'verifier-dddddddddddddddddddddddddddddddd',
            'nonce' => 'nonce-4',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_STATE_MISMATCH');
        $store->consume('google', 'wrong-state-value-aaaaaaaaaaaaaaaaaaaaaaaa');
    }
}
