<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Services\Orders\ManualCompletion\ManualCompletionException;
use App\Services\Orders\ManualCompletion\ManualCompletionGuard;
use App\Services\Orders\ManualCompletion\SettlementEvidence;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use iEXPackages\Transaction\Facades\TransactionFacade;
use iEXPackages\Transaction\Services\CurrencyAnalyticsService;
use iEXPackages\Transaction\Services\FundInvestmentService;
use iEXPackages\Transaction\Services\OrderProfitCalculator;
use iEXPackages\Transaction\Services\ProfitCalculationService;
use iEXPackages\Transaction\Services\ReferralBonusService;
use iEXPackages\Transaction\Services\ReserveProfitService;
use iEXPackages\Transaction\Services\UserWalletStoriesService;
use Tests\TestCase;

final class FakeCompleteOperator implements Authenticatable
{
    public function __construct(private readonly int $id, private readonly bool $canExecute)
    {
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(string $ability): bool
    {
        return $this->canExecute && $ability === 'admin_orders_execute';
    }
}

/**
 * Wave 1 manual completion safety. Mutating tests run inside a DB transaction and roll back.
 */
final class ManualCompletionSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->stubCompletionSideEffects();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Auth::forgetGuards();
        parent::tearDown();
    }

    public function test_evidence_rejects_blank_whitespace_oversize_and_objects(): void
    {
        foreach ([
            ['settlement_reference' => ''],
            ['settlement_reference' => '   '],
            ['settlement_reference' => "\n\t"],
        ] as $opts) {
            try {
                SettlementEvidence::fromOptions($opts);
                $this->fail('expected evidence required');
            } catch (ManualCompletionException $e) {
                $this->assertSame(422, $e->httpStatus);
                $this->assertSame('settlement_evidence_required', $e->errorCode);
            }
        }

        try {
            SettlementEvidence::fromOptions(['settlement_reference' => str_repeat('x', 192)]);
            $this->fail('expected oversized');
        } catch (ManualCompletionException $e) {
            $this->assertSame('settlement_evidence_invalid', $e->errorCode);
        }

        try {
            SettlementEvidence::fromOptions(['settlement_reference' => ['txid' => 'abc']]);
            $this->fail('expected object reject');
        } catch (ManualCompletionException $e) {
            $this->assertSame('settlement_evidence_invalid', $e->errorCode);
        }

        try {
            SettlementEvidence::fromOptions(['settlement_reference' => 'ab']);
            $this->fail('expected too short');
        } catch (ManualCompletionException $e) {
            $this->assertSame('settlement_evidence_invalid', $e->errorCode);
        }

        $ok = SettlementEvidence::fromOptions(['settlement_reference' => '  BANK-REF-991  ']);
        $this->assertSame('BANK-REF-991', $ok->reference);
        $this->assertSame('settlement_reference', $ok->source);
    }

    public function test_extra_fields_and_operator_note_are_valid_evidence(): void
    {
        $fromFields = SettlementEvidence::fromOptions([
            'otherFieldsForSuccess' => [
                ['name' => 'payout', 'value' => 'card *1111 sent'],
            ],
        ]);
        $this->assertSame('extra_fields', $fromFields->source);

        $fromNote = SettlementEvidence::fromOptions(['message' => 'cash desk Moscow']);
        $this->assertSame('message', $fromNote->source);
    }

    public function test_unauthorized_and_unauthenticated_manual_complete_rejected(): void
    {
        $task = $this->insertSyntheticTask(7);
        $guard = new ManualCompletionGuard();

        try {
            $guard->authorizeLockedTask($task, ManualCompletionGuard::SOURCE_MANUAL, [
                'settlement_reference' => 'REF-1',
            ], null);
            $this->fail('expected unauthenticated');
        } catch (ManualCompletionException $e) {
            $this->assertSame(401, $e->httpStatus);
        }

        try {
            $guard->authorizeLockedTask($task, ManualCompletionGuard::SOURCE_MANUAL, [
                'settlement_reference' => 'REF-1',
            ], new FakeCompleteOperator(9, false));
            $this->fail('expected forbidden');
        } catch (ManualCompletionException $e) {
            $this->assertSame(403, $e->httpStatus);
        }
    }

    public function test_missing_evidence_rejected_for_manual_source(): void
    {
        $task = $this->insertSyntheticTask(7);
        $this->expectException(ManualCompletionException::class);
        (new ManualCompletionGuard())->authorizeLockedTask(
            $task,
            ManualCompletionGuard::SOURCE_MANUAL,
            [],
            new FakeCompleteOperator(9, true)
        );
    }

    public function test_paid_to_completed_is_allowed_for_manual(): void
    {
        $task = $this->insertSyntheticTask(7);
        $decision = (new ManualCompletionGuard())->authorizeLockedTask(
            $task,
            ManualCompletionGuard::SOURCE_MANUAL,
            ['settlement_reference' => 'WIRE-7788'],
            new FakeCompleteOperator(9, true)
        );
        $this->assertFalse($decision['already_completed']);
        $this->assertSame(7, $decision['from_status']);
        $this->assertSame('WIRE-7788', $decision['settlement']['reference']);
    }

    public function test_invalid_current_state_rejected(): void
    {
        $task = $this->insertSyntheticTask(2);
        $this->expectException(ManualCompletionException::class);
        (new ManualCompletionGuard())->authorizeLockedTask(
            $task,
            ManualCompletionGuard::SOURCE_MANUAL,
            ['settlement_reference' => 'WIRE-7788'],
            new FakeCompleteOperator(9, true)
        );
    }

    public function test_set_status_completed_bypass_is_blocked(): void
    {
        $task = $this->insertSyntheticTask(7);
        $tx = TransactionFacade::find($task->id);
        try {
            $tx->setStatus(4);
            $this->fail('bypass should be blocked');
        } catch (ManualCompletionException $e) {
            $this->assertSame('completion_bypass_forbidden', $e->errorCode);
        }
        $this->assertSame(7, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_authorized_manual_complete_writes_status_and_settlement_once(): void
    {
        $task = $this->insertSyntheticTask(7);
        Auth::setUser(new FakeCompleteOperator(42, true));

        TransactionFacade::find($task->id)->success([
            'skip_auto_payment' => true,
            'completion_source' => ManualCompletionGuard::SOURCE_MANUAL,
            'settlement_reference' => 'SEPA-1001',
        ]);

        $fresh = Task::query()->whereKey($task->id)->first();
        $this->assertSame(4, (int) $fresh->status);
        $this->assertSame('SEPA-1001', $fresh->opt_params['manual_settlement']['reference'] ?? null);
        $this->assertSame(42, (int) $fresh->id_who_completed);

        $logs = DB::table('tasks_status_log')
            ->where('id_task', $task->id)
            ->where('new_status', 4)
            ->whereNull('deleted_at')
            ->count();
        $this->assertSame(1, $logs);
    }

    public function test_duplicate_retry_is_idempotent_without_second_status_log(): void
    {
        $task = $this->insertSyntheticTask(7);
        Auth::setUser(new FakeCompleteOperator(42, true));
        $opts = [
            'skip_auto_payment' => true,
            'completion_source' => ManualCompletionGuard::SOURCE_MANUAL,
            'settlement_reference' => 'SEPA-1001',
        ];
        TransactionFacade::find($task->id)->success($opts);

        try {
            TransactionFacade::find($task->id)->success($opts);
            $this->fail('retry should 409');
        } catch (ManualCompletionException $e) {
            $this->assertSame(409, $e->httpStatus);
            $this->assertSame('already_completed', $e->errorCode);
        }

        $this->assertSame(4, (int) Task::query()->whereKey($task->id)->value('status'));
        $this->assertSame(1, DB::table('tasks_status_log')->where('id_task', $task->id)->where('new_status', 4)->whereNull('deleted_at')->count());
        $this->assertSame('SEPA-1001', Task::query()->whereKey($task->id)->first()->opt_params['manual_settlement']['reference']);
    }

    public function test_double_click_same_operator_one_completion_effect(): void
    {
        $this->test_duplicate_retry_is_idempotent_without_second_status_log();
    }

    public function test_two_admin_sequential_second_sees_already_completed(): void
    {
        $task = $this->insertSyntheticTask(7);
        Auth::setUser(new FakeCompleteOperator(1, true));
        TransactionFacade::find($task->id)->success([
            'skip_auto_payment' => true,
            'completion_source' => ManualCompletionGuard::SOURCE_MANUAL,
            'settlement_reference' => 'ADMIN-A',
        ]);

        Auth::setUser(new FakeCompleteOperator(2, true));
        try {
            TransactionFacade::find($task->id)->success([
                'skip_auto_payment' => true,
                'completion_source' => ManualCompletionGuard::SOURCE_MANUAL,
                'settlement_reference' => 'ADMIN-B',
            ]);
            $this->fail('second admin should conflict');
        } catch (ManualCompletionException $e) {
            $this->assertSame('already_completed', $e->errorCode);
        }

        $fresh = Task::query()->whereKey($task->id)->first();
        $this->assertSame('ADMIN-A', $fresh->opt_params['manual_settlement']['reference']);
        $this->assertSame(1, (int) $fresh->id_who_completed);
        $this->assertSame(1, DB::table('tasks_status_log')->where('id_task', $task->id)->where('new_status', 4)->whereNull('deleted_at')->count());
    }

    public function test_row_lock_blocks_second_connection(): void
    {
        $liveId = (int) DB::table('tasks')->where('status', 7)->whereNull('deleted_at')->orderBy('id')->value('id');
        $this->assertGreaterThan(0, $liveId);
        Task::query()->whereKey($liveId)->lockForUpdate()->first();

        $cfg = config('database.connections.'.config('database.default'));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'] ?? 3306, $cfg['database']);
        $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('SET innodb_lock_wait_timeout=1');
        $pdo->beginTransaction();

        $blocked = false;
        try {
            $stmt = $pdo->prepare('SELECT id FROM tasks WHERE id = ? FOR UPDATE');
            $stmt->execute([$liveId]);
            $stmt->fetch();
        } catch (\PDOException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout')
                || str_contains($e->getMessage(), '1205');
        }
        $pdo->rollBack();
        $this->assertTrue($blocked, 'second connection must wait on lockForUpdate');
    }

    public function test_already_completed_guard_short_circuits(): void
    {
        $task = $this->insertSyntheticTask(4);
        $decision = (new ManualCompletionGuard())->authorizeLockedTask(
            $task,
            ManualCompletionGuard::SOURCE_MANUAL,
            ['settlement_reference' => 'IGNORED'],
            new FakeCompleteOperator(9, true)
        );
        $this->assertTrue($decision['already_completed']);
    }

    public function test_waiting_handle_to_completed_still_allowed(): void
    {
        $task = $this->insertSyntheticTask(3);
        $decision = (new ManualCompletionGuard())->authorizeLockedTask(
            $task,
            ManualCompletionGuard::SOURCE_MANUAL,
            ['settlement_reference' => 'NOTE-OK'],
            new FakeCompleteOperator(9, true)
        );
        $this->assertSame(3, $decision['from_status']);
    }

    private function insertSyntheticTask(int $status): Task
    {
        $id = DB::table('tasks')->insertGetId([
            'public_id' => 'w1s-'.bin2hex(random_bytes(6)),
            'status' => $status,
            'id_direction_exchange' => 25,
            'discount1' => 0,
            'discount2' => 0,
            'give_price' => 1,
            'receiving_price' => 1,
            'id_who_completed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }

    private function stubCompletionSideEffects(): void
    {
        app()->bind(FundInvestmentService::class, fn () => new class {
            public function invest($amount): void
            {
            }
        });
        app()->bind(OrderProfitCalculator::class, fn () => new class {
            public function calculateForTask($task, $amount)
            {
                return null;
            }
        });
        app()->bind(ProfitCalculationService::class, fn () => new class {
            public function calculateProfit($task, $amount): void
            {
            }
        });
        app()->bind(ReferralBonusService::class, fn () => new class {
            public function process($task = null): void
            {
            }
        });
        app()->bind(UserWalletStoriesService::class, fn () => new class {
            public function storeWallets($transaction): void
            {
            }
        });
        app()->bind(ReserveProfitService::class, fn () => new class {
            public function updateReserveAfterSuccess($reserve, $amount, $currency): void
            {
            }
        });
        app()->bind(CurrencyAnalyticsService::class, fn () => new class {
            public function updateStats($task, $in, $out): void
            {
            }
        });
    }
}
