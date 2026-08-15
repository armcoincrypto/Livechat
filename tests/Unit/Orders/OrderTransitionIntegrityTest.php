<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\ManualCompletion\ManualCompletionException;
use App\Services\Orders\Transitions\ConfirmedInboundPaidTransition;
use App\Services\Orders\Transitions\OrderTransitionException;
use App\Services\Orders\Transitions\OrderTransitionService;
use Illuminate\Support\Facades\DB;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Tests\TestCase;

final class OrderTransitionIntegrityTest extends TestCase
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

    public function test_canonical_financial_transitions(): void
    {
        $this->assertTrue(TaskStatusEnum::WAITING_HANDLE->canTransitionTo(TaskStatusEnum::PAID));
        $this->assertTrue(TaskStatusEnum::CHECK_PAYMENT->canTransitionTo(TaskStatusEnum::PAID));
        $this->assertTrue(TaskStatusEnum::MERCHANT_CONFIRMATION->canTransitionTo(TaskStatusEnum::PAID));
        $this->assertTrue(TaskStatusEnum::PAID->canTransitionTo(TaskStatusEnum::COMPLETED));
        $this->assertTrue(TaskStatusEnum::WAITING_HANDLE->canTransitionTo(TaskStatusEnum::COMPLETED));
        $this->assertFalse(TaskStatusEnum::COMPLETED->canTransitionTo(TaskStatusEnum::PAID));
        $this->assertFalse(TaskStatusEnum::EXPIRED->canTransitionTo(TaskStatusEnum::PAID));
    }

    public function test_raw_paid_write_without_permit_is_rejected(): void
    {
        $task = $this->insertTask(3);
        $tx = TransactionFacade::find($task->id);
        $this->expectException(OrderTransitionException::class);
        $tx->setStatus(7);
    }

    public function test_same_state_set_status_is_noop(): void
    {
        $task = $this->insertTask(3);
        $tx = TransactionFacade::find($task->id);
        $tx->setStatus(3);
        $this->assertSame(3, (int) Task::query()->whereKey($task->id)->value('status'));
        $this->assertSame(0, DB::table('tasks_status_log')->where('id_task', $task->id)->where('new_status', 3)->count());
    }

    public function test_confirmed_wallet_from_3_pays_exactly_once(): void
    {
        $task = $this->insertTask(3);
        $txid = 'w3txid'.bin2hex(random_bytes(8));
        $service = new ConfirmedInboundPaidTransition();
        $write = function () use ($task) {
            $tx = TransactionFacade::find($task->id);
            $tx->grantPaidWritePermit();
            $tx->setStatus(7);
        };
        $first = $service->apply((int) $task->id, ['txid' => $txid, 'amount' => 10, 'confirmations' => 1], $write);
        $this->assertSame('paid', $first['outcome']);
        $this->assertSame(7, (int) Task::query()->whereKey($task->id)->value('status'));
        $this->assertSame(1, DB::table('tasks_status_log')->where('id_task', $task->id)->where('old_status', 3)->where('new_status', 7)->whereNull('deleted_at')->count());

        $second = $service->apply((int) $task->id, ['txid' => $txid, 'amount' => 10, 'confirmations' => 1], $write);
        $this->assertSame('already_paid', $second['outcome']);
        $this->assertSame(1, DB::table('tasks_status_log')->where('id_task', $task->id)->where('old_status', 3)->where('new_status', 7)->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('id_task', $task->id)->whereNull('deleted_at')->count());
    }

    public function test_repeated_detection_one_hundred_times_one_paid(): void
    {
        $task = $this->insertTask(3);
        $txid = 'w3loop'.bin2hex(random_bytes(8));
        $service = new ConfirmedInboundPaidTransition();
        $write = function () use ($task) {
            $tx = TransactionFacade::find($task->id);
            $tx->grantPaidWritePermit();
            $tx->setStatus(7);
        };
        for ($i = 0; $i < 100; $i++) {
            $service->apply((int) $task->id, ['txid' => $txid, 'amount' => 1, 'confirmations' => 1], $write);
        }
        $this->assertSame(7, (int) Task::query()->whereKey($task->id)->value('status'));
        $this->assertSame(1, DB::table('tasks_status_log')->where('id_task', $task->id)->where('new_status', 7)->whereNull('deleted_at')->count());
    }

    public function test_paid_write_failure_is_observable(): void
    {
        $task = $this->insertTask(3);
        $service = new ConfirmedInboundPaidTransition();
        try {
            $service->apply((int) $task->id, ['txid' => 'fail'.bin2hex(random_bytes(4)), 'amount' => 1, 'confirmations' => 1], function () {
                // refuse to write PAID
            });
            $this->fail('expected transition failure');
        } catch (OrderTransitionException $e) {
            $this->assertSame('paid_transition_failed', $e->errorCode);
        }
        $this->assertSame(3, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_duplicate_txid_other_order_refuses_paid(): void
    {
        $a = $this->insertTask(3);
        $b = $this->insertTask(3);
        $txid = 'dup'.bin2hex(random_bytes(8));
        DB::table('wallet_transactions')->insert([
            'id_task' => $a->id,
            'amount' => '1',
            'txid' => $txid,
            'confirmations' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service = new ConfirmedInboundPaidTransition();
        $this->expectException(OrderTransitionException::class);
        $service->apply((int) $b->id, ['txid' => $txid, 'amount' => 1, 'confirmations' => 1], function () {});
    }

    public function test_completion_bypass_still_blocked(): void
    {
        $task = $this->insertTask(7);
        $tx = TransactionFacade::find($task->id);
        $this->expectException(ManualCompletionException::class);
        $tx->setStatus(4);
    }

    public function test_from_12_and_13_are_paid_from(): void
    {
        $this->assertTrue(OrderTransitionService::isPaidFrom(12));
        $this->assertTrue(OrderTransitionService::isPaidFrom(13));
        $this->assertFalse(OrderTransitionService::isPaidFrom(2));
    }

    public function test_row_lock_blocks_second_paid_writer(): void
    {
        $task = $this->insertTask(3);
        Task::query()->whereKey($task->id)->lockForUpdate()->first();
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

    private function insertTask(int $status): Task
    {
        $id = DB::table('tasks')->insertGetId([
            'public_id' => 'w3-'.bin2hex(random_bytes(6)),
            'status' => $status,
            'id_direction_exchange' => 25,
            'discount1' => 0,
            'discount2' => 0,
            'give_price' => 10,
            'receiving_price' => 10,
            'id_who_completed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }
}
