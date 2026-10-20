<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * Isolated SQLite schema for OAuth identity tests.
 * Never touches production DB (app_exswapin).
 */
final class OAuthSqliteHarness
{
    public const PRODUCTION_DB_NAME = 'app_exswapin';

    private static ?string $previousDefault = null;

    public static function bootIsolated(): void
    {
        $configuredMysql = (string) (Config::get('database.connections.mysql.database') ?? '');
        self::$previousDefault = (string) (Config::get('database.default') ?: 'mysql');

        Config::set('database.connections.oauth_sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('database.default', 'oauth_sqlite');
        DB::purge('oauth_sqlite');
        DB::reconnect('oauth_sqlite');
        DB::setDefaultConnection('oauth_sqlite');

        // Avoid database session driver hitting missing sessions table on sqlite.
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        Session::setDefaultDriver('array');

        $testDbName = (string) Config::get('database.connections.oauth_sqlite.database');
        if ($testDbName === self::PRODUCTION_DB_NAME || ($testDbName === $configuredMysql && $testDbName !== ':memory:')) {
            throw new RuntimeException('HARD_STOP: OAuth test DB matches production');
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('HARD_STOP: expected sqlite');
        }

        self::migrateMinimalSchema();
    }

    public static function restoreDefaultConnection(): void
    {
        $previous = self::$previousDefault ?: 'mysql';
        if ($previous === 'oauth_sqlite') {
            $previous = 'mysql';
        }
        Config::set('database.default', $previous);
        DB::purge('oauth_sqlite');
        DB::setDefaultConnection($previous);
        try {
            DB::reconnect($previous);
        } catch (\Throwable) {
        }
        self::$previousDefault = null;
    }

    public static function migrateMinimalSchema(): void
    {
        Schema::dropIfExists('user_oauth_identities');
        Schema::dropIfExists('reward_programs');
        Schema::dropIfExists('banned');
        Schema::dropIfExists('users');

        Schema::create('reward_programs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('banned', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->nullable();
            $table->string('filter_key')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->integer('deactivation')->default(0);
            $table->timestamp('banned_at')->nullable();
            $table->text('allowed_ip_addresses')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('language')->nullable();
            $table->integer('is_frontend')->default(0);
            $table->unsignedInteger('id_reward_program')->nullable();
            $table->timestamps();
        });

        Schema::create('user_oauth_identities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('provider', 32);
            $table->string('provider_subject', 191);
            $table->string('provider_email', 191)->nullable();
            $table->boolean('provider_email_verified')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_subject']);
            $table->unique(['user_id', 'provider']);
        });
    }
}
