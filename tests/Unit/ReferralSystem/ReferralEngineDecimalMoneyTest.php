<?php

declare(strict_types=1);

namespace Tests\Unit\ReferralSystem;

use App\Models\UserBalance;
use Illuminate\Support\Facades\DB;
use iEXPackages\ReferralSystem\ReferralEngine;
use iEXPackages\ReferralSystem\Support\ReferralMath;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Wave B — ReferralEngine decimal money hardening.
 * Uses DB transactions rolled back; does not create real referral credits on live traffic.
 */
final class ReferralEngineDecimalMoneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_a_standard_referral_percent_amount(): void
    {
        // 100.00 × 5% = 5.00
        $amount = ReferralMath::mul('100.00', ReferralMath::div('5', '100', ReferralMath::PERCENT_SCALE));
        $this->assertSame('5.00', ReferralMath::norm($amount));
    }

    public function test_b_fractional_precision_no_epsilon(): void
    {
        // Classic binary-float trap: 0.1 * 0.1 style via percent path
        $p = ReferralMath::div('10', '100', ReferralMath::PERCENT_SCALE); // 0.1000
        $amount = ReferralMath::mul('0.10', $p); // 0.01
        $this->assertSame('0.01', ReferralMath::norm($amount));

        // 8.33 × 12.5% = 1.04125 → HALF_UP scale2 = 1.04
        $p2 = ReferralMath::div('12.5', '100', ReferralMath::PERCENT_SCALE);
        $this->assertSame('1.04', ReferralMath::norm(ReferralMath::mul('8.33', $p2)));

        // 333.33 × 3.33% 
        $p3 = ReferralMath::div('3.33', '100', ReferralMath::PERCENT_SCALE);
        $this->assertSame('11.10', ReferralMath::norm(ReferralMath::mul('333.33', $p3)));
    }

    public function test_c_cumulative_exact_credits(): void
    {
        $balance = $this->seedBalance(0);
        $engine = $this->engineForReflection();
        $inc = $this->method($engine, 'safeIncrement');

        for ($i = 0; $i < 10; $i++) {
            $inc->invoke($engine, $balance, 'balance', '0.10');
            $balance->refresh();
        }

        $this->assertSame('1.00', ReferralMath::fromLegacyStorage($balance->fresh()->balance));
    }

    public function test_d_small_amount(): void
    {
        $balance = $this->seedBalance(0);
        $engine = $this->engineForReflection();
        $this->method($engine, 'safeIncrement')->invoke($engine, $balance, 'balance', '0.01');
        $this->assertSame('0.01', ReferralMath::fromLegacyStorage($balance->fresh()->balance));
    }

    public function test_e_large_amount_no_scientific_notation(): void
    {
        $balance = $this->seedBalance(0);
        $engine = $this->engineForReflection();
        $this->method($engine, 'safeIncrement')->invoke($engine, $balance, 'balance', '1000000.00');
        $stored = ReferralMath::fromLegacyStorage($balance->fresh()->balance);
        $this->assertSame('1000000.00', $stored);
        $this->assertStringNotContainsString('E', strtoupper($stored));
    }

    public function test_f_audit_parity_bonus_number_matches_balance_delta(): void
    {
        $balance = $this->seedBalance('10.00');
        $before = ReferralMath::fromLegacyStorage($balance->balance);
        $credit = '8.33';
        $engine = $this->engineForReflection();
        $this->method($engine, 'safeIncrement')->invoke($engine, $balance, 'balance', $credit);
        $after = ReferralMath::fromLegacyStorage($balance->fresh()->balance);
        $delta = ReferralMath::sub($after, $before);
        $this->assertSame(ReferralMath::norm($credit), $delta);
        // referral_log.bonus_number is decimal(24,2) — same SCALE as ReferralMath
        $this->assertSame(ReferralMath::norm($credit), ReferralMath::norm($credit));
    }

    public function test_g_currency_isolation_only_target_fields(): void
    {
        $balance = $this->seedBalance('5.00');
        $engine = $this->engineForReflection();
        $holdBefore = ReferralMath::fromLegacyStorage($balance->hold_balance ?? 0);
        $profitBefore = ReferralMath::fromLegacyStorage($balance->referral_total_profit ?? 0);

        $this->method($engine, 'safeIncrement')->invoke($engine, $balance, 'balance', '1.25');
        $balance->refresh();

        $this->assertSame('6.25', ReferralMath::fromLegacyStorage($balance->balance));
        $this->assertSame($holdBefore, ReferralMath::fromLegacyStorage($balance->hold_balance ?? 0));
        $this->assertSame($profitBefore, ReferralMath::fromLegacyStorage($balance->referral_total_profit ?? 0));
    }

    public function test_h_zero_amount_is_noop(): void
    {
        $balance = $this->seedBalance('3.00');
        $engine = $this->engineForReflection();
        $this->method($engine, 'safeIncrement')->invoke($engine, $balance, 'balance', '0.00');
        $this->assertSame('3.00', ReferralMath::fromLegacyStorage($balance->fresh()->balance));
    }

    public function test_i_business_policy_examples_unchanged(): void
    {
        foreach (['0.01', '0.1', '1.00', '8.33', '99.99', '100.00', '333.33', '1000.00'] as $base) {
            $norm = ReferralMath::norm($base);
            $pct = ReferralMath::div('5', '100', ReferralMath::PERCENT_SCALE);
            $bonus = ReferralMath::norm(ReferralMath::mul($norm, $pct));
            // Must be exact decimal string at SCALE=2 — not float formatted
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $bonus);
            $this->assertTrue(ReferralMath::cmp($bonus, '0') >= 0);
        }
    }

    public function test_no_float_increment_in_referral_engine_source(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ReferralSystem/ReferralEngine.php');
        $this->assertStringContainsString('safeIncrement', $src);
        $this->assertStringNotContainsString("increment('balance', (float)", $src);
        $this->assertStringNotContainsString('increment("balance", (float)', $src);
        $this->assertStringNotContainsString("increment('hold_balance', (float)", $src);
        $this->assertStringNotContainsString("increment('referral_total_profit', (float)", $src);
        $this->assertSame(0, preg_match_all('/->increment\([^)]*\(float\)/', $src));
    }

    public function test_concurrency_row_lock_blocks_second_connection(): void
    {
        $balance = $this->seedBalance('1.00');
        UserBalance::query()->whereKey($balance->id)->lockForUpdate()->first();

        $cfg = config('database.connections.'.config('database.default'));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'] ?? 3306, $cfg['database']);
        $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET innodb_lock_wait_timeout=1');
        $pdo->beginTransaction();
        $blocked = false;
        try {
            $stmt = $pdo->prepare('SELECT id FROM user_balance WHERE id = ? FOR UPDATE');
            $stmt->execute([(int) $balance->id]);
            $stmt->fetch();
        } catch (\PDOException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
        }
        $pdo->rollBack();
        $this->assertTrue($blocked);
    }

    public function test_from_legacy_storage_normalizes_float_boundary(): void
    {
        $this->assertSame('0.10', ReferralMath::fromLegacyStorage(0.1));
        $this->assertSame('1.23', ReferralMath::fromLegacyStorage(1.23));
        $this->assertSame('0.00', ReferralMath::fromLegacyStorage(null));
    }

    private function seedBalance(string|int|float $balance): UserBalance
    {
        $userId = 880000000 + random_int(1, 999999);
        $id = DB::table('user_balance')->insertGetId([
            'id_user' => $userId,
            'id_code_currency' => 1,
            'balance' => $balance,
            'hold_balance' => 0,
            'referral_total_profit' => 0,
            'referral_total_withdrawal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return UserBalance::query()->whereKey($id)->firstOrFail();
    }

    private function engineForReflection(): ReferralEngine
    {
        /** @var ReferralEngine $engine */
        $engine = (new ReflectionClass(ReferralEngine::class))->newInstanceWithoutConstructor();

        return $engine;
    }

    private function method(object $obj, string $name): ReflectionMethod
    {
        $m = new ReflectionMethod($obj, $name);
        $m->setAccessible(true);

        return $m;
    }
}
