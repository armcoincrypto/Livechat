<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Filters\OrderFilter;
use App\Models\Task;
use App\Services\Orders\OperatorStatusVocabulary;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WaveAPaidQueueFilterTest extends TestCase
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

    public function test_paid_age_filter_returns_only_human_paid(): void
    {
        $paidFresh = $this->insertPaid(now()->subMinutes(10));
        $paidOld = $this->insertPaid(now()->subHours(30));
        $bot = $this->insertPaid(now()->subHours(30), isBot: true);
        $waiting = $this->insertStatus(3, now()->subHours(30));

        $idsAll = Task::filter(['paidAge' => 'paid'])->pluck('id')->all();
        $this->assertContains($paidFresh->id, $idsAll);
        $this->assertContains($paidOld->id, $idsAll);
        $this->assertNotContains($bot->id, $idsAll);
        $this->assertNotContains($waiting->id, $idsAll);

        $ids24 = Task::filter(['paidAge' => 'gt24h'])->pluck('id')->all();
        $this->assertContains($paidOld->id, $ids24);
        $this->assertNotContains($paidFresh->id, $ids24);
    }

    public function test_vocabulary_labels_for_critical_statuses(): void
    {
        $this->assertStringContainsString('settlement', strtolower(OperatorStatusVocabulary::adminLabel(7)));
        $this->assertStringContainsString('marked paid', strtolower(OperatorStatusVocabulary::adminLabel(3)));
        $this->assertStringContainsString('подтверждена', OperatorStatusVocabulary::telegramLabel(7));
        $this->assertStringContainsString('отметил', OperatorStatusVocabulary::telegramLabel(3));
    }

    public function test_list_mapper_does_not_expose_email_or_wallet_in_paid_fields(): void
    {
        $task = $this->insertPaid(now()->subHours(2));
        $task->load(['task_status', 'user', 'direction_exchange']);
        $row = \App\Http\Resources\Admin\Orders\OrdersListRowMapper::map($task);
        $this->assertArrayHasKey('paid_age_human', $row['attributes']);
        $this->assertArrayNotHasKey('transfer_to_account', $row['attributes']);
        $this->assertArrayNotHasKey('email', $row['attributes']);
        $this->assertArrayNotHasKey('wallet', $row['attributes']);
    }

    private function insertPaid(\DateTimeInterface $paidAt, bool $isBot = false): Task
    {
        return $this->insertStatus(7, $paidAt, $isBot);
    }

    private function insertStatus(int $status, \DateTimeInterface $at, bool $isBot = false): Task
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
            'is_bot' => $isBot ? 1 : 0,
            'is_archive' => 0,
            'started_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return Task::query()->whereKey($id)->firstOrFail();
    }
}
