<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Services\Orders\Reconciliation\FundedWrongStatusRepairer;
use App\Services\Orders\Reconciliation\FundedWrongStatusRepairException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FundedWrongStatusRepairerTest extends TestCase
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

    public function test_allowlisted_confirmed_funds_promotes_3_to_7_once(): void
    {
        [$task, $txid, $repairer] = $this->seedAllowlistedWrongStatus();

        $first = $repairer->repair((int) $task->id);
        $this->assertSame('repaired', $first['outcome']);
        $this->assertSame(3, $first['from_status']);
        $this->assertSame(7, $first['to_status']);
        $this->assertSame(1, $first['status_log_3_to_7']);

        $fresh = Task::query()->whereKey($task->id)->first();
        $this->assertSame(7, (int) $fresh->status);
        $this->assertNull($fresh->completed_at);
        $this->assertNotNull($fresh->opt_params['funded_wrong_status_repair'] ?? null);

        $second = $repairer->repair((int) $task->id);
        $this->assertSame('already_repaired', $second['outcome']);
        $this->assertSame(1, $second['status_log_3_to_7']);
        $this->assertSame(7, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_refuses_task_not_on_allowlist(): void
    {
        $task = $this->insertTask(3);
        $repairer = new FundedWrongStatusRepairer([]);
        $this->expectException(FundedWrongStatusRepairException::class);
        $repairer->repair((int) $task->id);
    }

    public function test_refuses_without_txid(): void
    {
        $task = $this->insertTask(3);
        $repairer = new FundedWrongStatusRepairer([
            (int) $task->id => [
                'public_id' => (string) $task->public_id,
                'from_status' => 3,
                'to_status' => 7,
                'txid' => 'deadbeef',
            ],
        ]);
        try {
            $repairer->repair((int) $task->id);
            $this->fail('expected evidence failure');
        } catch (FundedWrongStatusRepairException $e) {
            $this->assertSame('evidence_failed', $e->errorCode);
        }
        $this->assertSame(3, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_duplicate_txid_on_other_task_is_refused(): void
    {
        [$task, $txid, $repairer] = $this->seedAllowlistedWrongStatus();
        $other = $this->insertTask(3);
        DB::table('wallet_transactions')->insert([
            'id_task' => $other->id,
            'amount' => '1',
            'txid' => $txid,
            'confirmations' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            $repairer->repair((int) $task->id);
            $this->fail('expected duplicate refuse');
        } catch (FundedWrongStatusRepairException $e) {
            $this->assertSame('evidence_failed', $e->errorCode);
        }
        $this->assertSame(3, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    /**
     * @return array{0:Task,1:string,2:FundedWrongStatusRepairer}
     */
    private function seedAllowlistedWrongStatus(): array
    {
        $task = $this->insertTask(3);
        $txid = 'testtxid'.bin2hex(random_bytes(8));
        DB::table('wallet_transactions')->insert([
            'id_task' => $task->id,
            'amount' => '154.69973945',
            'txid' => $txid,
            'confirmations' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $repairer = new FundedWrongStatusRepairer([
            (int) $task->id => [
                'public_id' => (string) $task->public_id,
                'from_status' => 3,
                'to_status' => 7,
                'txid' => $txid,
            ],
        ]);

        return [$task, $txid, $repairer];
    }

    private function insertTask(int $status): Task
    {
        $id = DB::table('tasks')->insertGetId([
            'public_id' => 'w25-'.bin2hex(random_bytes(6)),
            'status' => $status,
            'id_direction_exchange' => 25,
            'discount1' => 0,
            'discount2' => 0,
            'give_price' => 154.695,
            'receiving_price' => 1,
            'id_who_completed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }
}
