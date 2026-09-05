<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\ManualCompletion\ManualCompletionException;
use App\Services\Orders\ManualCompletion\SettlementEvidence;
use App\Services\Orders\PaidOrderAge;
use App\Services\Orders\Transitions\OrderTransitionException;
use App\Services\Orders\Transitions\OrderTransitionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Tests\TestCase;

/**
 * Wave A — funded reject safety + PAID age helpers.
 */
final class WaveAFundedRejectSafetyTest extends TestCase
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

    public function test_enum_blocks_paid_to_rejected_allows_paid_to_frozen(): void
    {
        $this->assertFalse(TaskStatusEnum::PAID->canTransitionTo(TaskStatusEnum::REJECTED));
        $this->assertFalse(TaskStatusEnum::COMPLETED->canTransitionTo(TaskStatusEnum::REJECTED));
        $this->assertTrue(TaskStatusEnum::PAID->canTransitionTo(TaskStatusEnum::FROZEN));
        $this->assertTrue(TaskStatusEnum::PAID->canTransitionTo(TaskStatusEnum::COMPLETED));
        $this->assertTrue(TaskStatusEnum::WAITING_HANDLE->canTransitionTo(TaskStatusEnum::REJECTED));
    }

    public function test_paid_to_reject_via_set_status_blocked(): void
    {
        $task = $this->insertTask(7);
        $tx = TransactionFacade::find($task->id);
        $this->expectException(OrderTransitionException::class);
        try {
            $tx->setStatus(5);
        } catch (OrderTransitionException $e) {
            $this->assertSame('funded_reject_forbidden', $e->errorCode);
            $this->assertSame(7, (int) Task::query()->whereKey($task->id)->value('status'));
            throw $e;
        }
    }

    public function test_completed_to_reject_blocked(): void
    {
        $task = $this->insertTask(4);
        $tx = TransactionFacade::find($task->id);
        $this->expectException(OrderTransitionException::class);
        try {
            $tx->setStatus(5);
        } catch (OrderTransitionException $e) {
            $this->assertSame('final_status_locked', $e->errorCode);
            throw $e;
        }
    }

    public function test_unpaid_waiting_handle_reject_still_allowed(): void
    {
        $task = $this->insertTask(3);
        $tx = TransactionFacade::find($task->id);
        $tx->setStatus(5);
        $this->assertSame(5, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_pending_reject_still_allowed(): void
    {
        $task = $this->insertTask(2);
        $tx = TransactionFacade::find($task->id);
        $tx->setStatus(5);
        $this->assertSame(5, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_reject_method_on_paid_is_blocked(): void
    {
        $task = $this->insertTask(7);
        $tx = TransactionFacade::find($task->id);
        $this->expectException(OrderTransitionException::class);
        $tx->reject();
    }

    public function test_paid_to_frozen_via_defer_allowed(): void
    {
        $task = $this->insertTask(7);
        $tx = TransactionFacade::find($task->id);
        $tx->setDeferType(1);
        $tx->defer();
        $this->assertSame(8, (int) Task::query()->whereKey($task->id)->value('status'));
    }

    public function test_canned_telegram_note_rejected_as_evidence(): void
    {
        $this->expectException(ManualCompletionException::class);
        SettlementEvidence::fromOptions([
            'message' => 'Telegram operator confirmed payout for order 12345',
        ]);
    }

    public function test_real_settlement_reference_accepted(): void
    {
        $ev = SettlementEvidence::fromOptions([
            'settlement_reference' => 'txid:abc123def456',
        ]);
        $this->assertSame('txid:abc123def456', $ev->reference);
        $this->assertSame('settlement_reference', $ev->source);
    }

    public function test_paid_age_buckets_and_human(): void
    {
        $now = Carbon::parse('2026-09-05 12:00:00');
        Carbon::setTestNow($now);

        $fresh = (object) ['started_at' => '2026-09-05 11:50:00', 'updated_at' => '2026-09-05 11:50:00'];
        $this->assertSame('fresh', PaidOrderAge::agingLevel($fresh, $now));
        $this->assertStringContainsString('min ago', (string) PaidOrderAge::human($fresh, $now));

        $h2 = (object) ['started_at' => '2026-09-05 09:00:00', 'updated_at' => '2026-09-05 09:00:00'];
        $this->assertSame('attention', PaidOrderAge::agingLevel($h2, $now));

        $h8 = (object) ['started_at' => '2026-09-05 03:00:00', 'updated_at' => '2026-09-05 03:00:00'];
        $this->assertSame('warn', PaidOrderAge::agingLevel($h8, $now));

        $d3 = (object) ['started_at' => '2026-09-02 12:00:00', 'updated_at' => '2026-09-02 12:00:00'];
        $this->assertSame('critical', PaidOrderAge::agingLevel($d3, $now));
        $this->assertSame('Paid 3 days ago', PaidOrderAge::human($d3, $now));

        $this->assertTrue(PaidOrderAge::isValidBucket('gt24h'));
        $this->assertFalse(PaidOrderAge::isValidBucket('nope'));
        $this->assertSame(24, PaidOrderAge::bucketMinHours('gt24h'));

        Carbon::setTestNow();
    }

    public function test_assert_reject_allowed_matrix(): void
    {
        OrderTransitionService::assertRejectAllowed(2);
        OrderTransitionService::assertRejectAllowed(3);
        $this->expectException(OrderTransitionException::class);
        OrderTransitionService::assertRejectAllowed(7);
    }

    private function insertTask(int $status): Task
    {
        $id = DB::table('tasks')->insertGetId([
            'public_id' => (string) random_int(900000000000, 999999999999),
            'status' => $status,
            'id_direction_exchange' => 25,
            'discount1' => 0,
            'discount2' => 0,
            'give_price' => 10,
            'receiving_price' => 10,
            'id_who_completed' => 0,
            'is_bot' => 0,
            'is_archive' => 0,
            'started_at' => $status === 7 ? now()->subHour() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }
}
