<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\PaymentDestinationFailClosedGuard;
use App\Services\Orders\Transitions\FailClosedPaymentDestinationTransition;
use App\Services\Orders\Transitions\OrderTransitionException;
use App\Services\Orders\Transitions\OrderTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class FailClosedPaymentDestinationTransitionTest extends TestCase
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

    public function test_a_valid_fail_close_park_reaches_rejected_with_audit(): void
    {
        Log::spy();
        $task = $this->insertTask(TaskStatusEnum::PENDING_PAYMENT->value, give: '12.34');
        $balanceBefore = (int) DB::table('user_balance_log')->count();

        (new FailClosedPaymentDestinationTransition())->parkNotPayable($task);

        $task->refresh();
        $this->assertSame(TaskStatusEnum::REJECTED->value, (int) $task->status);
        $opt = is_array($task->opt_params) ? $task->opt_params : [];
        $this->assertArrayHasKey(FailClosedPaymentDestinationTransition::OPT_KEY, $opt);
        $this->assertSame(
            FailClosedPaymentDestinationTransition::REASON_PRE_ISSUE_PARK,
            $opt[FailClosedPaymentDestinationTransition::OPT_KEY]['reason'] ?? null
        );
        $this->assertSame('12.34', (string) $task->give_price);
        $this->assertSame($balanceBefore, (int) DB::table('user_balance_log')->count());

        Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx) use ($task) {
            return $msg === 'order_transition_fail_closed'
                && (int) ($ctx['task_id'] ?? 0) === (int) $task->id
                && (int) ($ctx['from_status'] ?? -1) === TaskStatusEnum::PENDING_PAYMENT->value
                && (int) ($ctx['to_status'] ?? -1) === TaskStatusEnum::REJECTED->value
                && ($ctx['transition_owner'] ?? '') === FailClosedPaymentDestinationTransition::class;
        })->atLeast()->once();
    }

    public function test_b_guard_source_routes_through_canonical_transition_owner(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/PaymentDestinationFailClosedGuard.php');
        $this->assertStringContainsString('FailClosedPaymentDestinationTransition', $src);
        $this->assertStringContainsString('parkNotPayable', $src);
        $this->assertStringContainsString('restorePending', $src);
        $this->assertStringContainsString('ensureRejected', $src);
        $this->assertStringNotContainsString("forceFill(['status'", $src);
        $this->assertStringNotContainsString('forceFill(["status"', $src);
    }

    public function test_c_invalid_source_state_refuses_park(): void
    {
        $task = $this->insertTask(TaskStatusEnum::PAID->value);
        $this->expectException(OrderTransitionException::class);
        (new FailClosedPaymentDestinationTransition())->parkNotPayable($task);
    }

    public function test_c_restore_without_park_marker_refused(): void
    {
        $task = $this->insertTask(TaskStatusEnum::REJECTED->value);
        $this->expectException(OrderTransitionException::class);
        (new FailClosedPaymentDestinationTransition())->restorePending($task);
    }

    public function test_d_idempotent_double_park_and_ensure(): void
    {
        $task = $this->insertTask(TaskStatusEnum::PENDING_PAYMENT->value);
        $tx = new FailClosedPaymentDestinationTransition();
        $tx->parkNotPayable($task);
        $firstRejectedAt = (string) $task->fresh()->updated_at;
        $logsBefore = (int) DB::table('tasks_status_log')->where('id_task', $task->id)->where('new_status', TaskStatusEnum::REJECTED->value)->count();

        $tx->parkNotPayable($task->fresh());
        $tx->ensureRejected($task->fresh());

        $task->refresh();
        $this->assertSame(TaskStatusEnum::REJECTED->value, (int) $task->status);
        $logsAfter = (int) DB::table('tasks_status_log')->where('id_task', $task->id)->where('new_status', TaskStatusEnum::REJECTED->value)->count();
        $this->assertSame($logsBefore, $logsAfter);
        $this->assertSame($firstRejectedAt, (string) $task->updated_at);
    }

    public function test_d_restore_after_park_is_idempotent_second_call(): void
    {
        $task = $this->insertTask(TaskStatusEnum::PENDING_PAYMENT->value);
        $tx = new FailClosedPaymentDestinationTransition();
        $tx->parkNotPayable($task);
        $tx->restorePending($task->fresh());
        $this->assertSame(TaskStatusEnum::PENDING_PAYMENT->value, (int) $task->fresh()->status);

        // Already PENDING: second restore is a same-status noop (idempotent).
        $tx->restorePending($task->fresh());
        $this->assertSame(TaskStatusEnum::PENDING_PAYMENT->value, (int) $task->fresh()->status);
    }

    public function test_e_concurrency_lock_blocks_second_connection(): void
    {
        $task = $this->insertTask(TaskStatusEnum::PENDING_PAYMENT->value);
        DB::table('tasks')->where('id', $task->id)->lockForUpdate()->first();

        $cfg = config('database.connections.'.config('database.default'));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'] ?? 3306, $cfg['database']);
        $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET innodb_lock_wait_timeout=1');
        $pdo->beginTransaction();
        $blocked = false;
        try {
            $stmt = $pdo->prepare('SELECT id FROM tasks WHERE id = ? FOR UPDATE');
            $stmt->execute([(int) $task->id]);
            $stmt->fetch();
        } catch (\PDOException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
        }
        $pdo->rollBack();
        $this->assertTrue($blocked);
    }

    public function test_f_financial_non_mutation_on_park_restore(): void
    {
        $task = $this->insertTask(TaskStatusEnum::PENDING_PAYMENT->value, give: '99.50', recv: '88.25');
        $bal = (int) DB::table('user_balance_log')->count();
        $pays = (int) DB::table('pays_transaction_data')->count();
        $tx = new FailClosedPaymentDestinationTransition();
        $tx->parkNotPayable($task);
        $tx->restorePending($task->fresh());
        $task->refresh();
        $this->assertSame('99.50', (string) $task->give_price);
        $this->assertSame('88.25', (string) $task->receiving_price);
        $this->assertSame($bal, (int) DB::table('user_balance_log')->count());
        $this->assertSame($pays, (int) DB::table('pays_transaction_data')->count());
        $this->assertSame(TaskStatusEnum::PENDING_PAYMENT->value, (int) $task->status);
    }

    public function test_decide_helpers(): void
    {
        $this->assertTrue(PaymentDestinationFailClosedGuard::destinationRequired('kobbopay'));
        $this->assertSame(
            ['reject' => true, 'restore_pending' => false],
            PaymentDestinationFailClosedGuard::decide('kobbopay', false)
        );
        $this->assertSame(
            ['reject' => false, 'restore_pending' => true],
            PaymentDestinationFailClosedGuard::decide('kobbopay', true)
        );
    }

    public function test_assert_fail_closed_matrix(): void
    {
        $task = $this->insertTask(TaskStatusEnum::PENDING_PAYMENT->value);
        OrderTransitionService::assertFailClosedTransition(
            TaskStatusEnum::PENDING_PAYMENT->value,
            TaskStatusEnum::REJECTED->value,
            't',
            $task
        );
        $this->expectException(OrderTransitionException::class);
        OrderTransitionService::assertFailClosedTransition(
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::REJECTED->value,
            't',
            $task
        );
    }

    private function insertTask(int $status, string $give = '10', string $recv = '10'): Task
    {
        $id = DB::table('tasks')->insertGetId([
            'public_id' => (string) (9000000000000 + random_int(1, 999999999)),
            'status' => $status,
            'id_direction_exchange' => 25,
            'discount1' => 0,
            'discount2' => 0,
            'give_price' => $give,
            'receiving_price' => $recv,
            'id_who_completed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }
}
