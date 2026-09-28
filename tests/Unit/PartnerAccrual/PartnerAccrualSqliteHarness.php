<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Fail-closed SQLite isolation for partner accrual Wave A tests.
 * Never touches production DB (app_exswapin).
 */
final class PartnerAccrualSqliteHarness
{
    public const PRODUCTION_DB_NAME = 'app_exswapin';

    public static function bootIsolated(): void
    {
        $productionName = self::PRODUCTION_DB_NAME;
        $configuredMysql = (string) (Config::get('database.connections.mysql.database') ?? '');

        Config::set('database.connections.partner_accrual_sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('database.default', 'partner_accrual_sqlite');
        DB::purge('partner_accrual_sqlite');
        DB::reconnect('partner_accrual_sqlite');
        DB::setDefaultConnection('partner_accrual_sqlite');

        $testDbName = (string) Config::get('database.connections.partner_accrual_sqlite.database');

        if ($testDbName === self::PRODUCTION_DB_NAME || $testDbName === $configuredMysql && $testDbName !== ':memory:') {
            throw new RuntimeException(
                'HARD_STOP: TEST_DB matches production. TEST_DB_NAME='.$testDbName.' PRODUCTION_DB_NAME='.$productionName
            );
        }

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            throw new RuntimeException('HARD_STOP: expected sqlite test connection, got '.$driver);
        }

        self::migrateMinimalSchema();
        // Legacy Wave B/C fixtures pass raw RNG nonces; production create requires signed v1 tokens.
        Config::set('partner_accrual.allow_unbound_schedule_seed', true);

        fwrite(STDOUT, "TEST_DB_NAME={$testDbName}\n");
        fwrite(STDOUT, "PRODUCTION_DB_NAME={$productionName}\n");
        fwrite(STDOUT, "ISOLATED=YES\n");
    }

    public static function migrateMinimalSchema(): void
    {
        Schema::dropIfExists('partner_accrual_plan_events');
        Schema::dropIfExists('partner_accrual_installments');
        Schema::dropIfExists('partner_accrual_plans');
        Schema::dropIfExists('user_balance_log');
        Schema::dropIfExists('user_balance');
        Schema::dropIfExists('users');
        Schema::dropIfExists('code_currency');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->integer('deactivation')->default(0);
            $table->timestamps();
        });

        Schema::create('code_currency', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('user_balance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user')->unique();
            $table->double('balance')->default(0);
            $table->unsignedInteger('id_code_currency')->default(8);
            $table->double('referral_total_profit')->default(0);
            $table->double('referral_total_withdrawal')->default(0);
            $table->timestamps();
        });

        Schema::create('user_balance_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->integer('type')->default(0);
            $table->unsignedBigInteger('id_manager')->default(0);
            $table->text('text')->nullable();
            $table->string('from_balance')->nullable();
            $table->string('to_balance')->nullable();
            $table->integer('route_type')->default(0);
            $table->string('source', 64)->nullable();
            $table->string('reference', 191)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('partner_accrual_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('currency_code', 16)->default('USD');
            $table->unsignedInteger('currency_code_id')->nullable();
            $table->decimal('total_amount', 20, 8);
            $table->decimal('credited_amount', 20, 8)->default('0');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 32)->default('DRAFT');
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('schedule_seed', 64)->nullable();
            $table->decimal('min_installment', 20, 8)->nullable();
            $table->decimal('max_installment', 20, 8)->nullable();
            $table->unsignedSmallInteger('variance_percent')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_accrual_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedInteger('sequence');
            $table->date('scheduled_for');
            $table->decimal('amount', 20, 8);
            $table->string('status', 32)->default('PENDING');
            $table->string('idempotency_key', 191);
            $table->unsignedBigInteger('balance_log_id')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'sequence']);
            $table->unique('idempotency_key');
        });

        Schema::create('partner_accrual_plan_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('event_type', 64)->index();
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('code_currency')->insert([
            'id' => 8,
            'name' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fail-closed defaults for tests unless explicitly enabled.
        config([
            'partner_accrual.enabled' => false,
            'partner_accrual.scheduler_enabled' => false,
            'partner_accrual.allow_apply' => false,
            'partner_accrual.business_timezone' => 'Europe/Moscow',
            'partner_accrual.max_installments_per_run' => 50,
            'partner_accrual.default_variance_percent' => 25,
            'partner_accrual.minor_unit_scale' => 2,
            'app.timezone' => 'Europe/Moscow',
        ]);
    }
}
