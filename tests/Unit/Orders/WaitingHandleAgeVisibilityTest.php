<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Http\Resources\Admin\Orders\LiveOrdersRowMapper;
use App\Services\Orders\WaitingHandleAge;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class WaitingHandleAgeVisibilityTest extends TestCase
{
    public function test_human_age_and_claim_prompt_for_unclaimed_stale(): void
    {
        $now = Carbon::parse('2026-10-18 12:00:00');
        $task = (object) [
            'status' => 3,
            'started_at' => '2026-10-17 10:00:00',
            'updated_at' => '2026-10-17 10:00:00',
            'in_flow_funds' => 0,
            'register_tx' => 0,
            'task_operators' => [],
        ];

        $human = WaitingHandleAge::human($task, $now);
        $this->assertNotNull($human);
        $this->assertStringContainsString('Marked paid', $human);
        $this->assertSame('critical', WaitingHandleAge::agingLevel($task, $now));
        $this->assertStringContainsString('UNCLAIMED', (string) WaitingHandleAge::claimPrompt($task, $now));
        $this->assertSame('no_funds_signal', WaitingHandleAge::paymentSignal($task));
    }

    public function test_claimed_order_has_no_claim_prompt(): void
    {
        $now = Carbon::parse('2026-10-18 12:00:00');
        $op = (object) ['user' => (object) ['id' => 1, 'name' => 'Op']];
        $task = (object) [
            'status' => 3,
            'started_at' => '2026-10-17 10:00:00',
            'updated_at' => '2026-10-17 10:00:00',
            'in_flow_funds' => 1,
            'register_tx' => 0,
            'task_operators' => [$op],
        ];

        $this->assertTrue(WaitingHandleAge::isClaimed($task));
        $this->assertNull(WaitingHandleAge::claimPrompt($task, $now));
        $this->assertSame('in_flow_funds', WaitingHandleAge::paymentSignal($task));
    }

    public function test_live_mapper_surfaces_age_claim_and_signal_without_status_mutation(): void
    {
        $task = TaskStub::unclaimedWaitingHandle();
        $row = LiveOrdersRowMapper::map($task, 1);
        $this->assertSame(3, (int) $row['status_int']);
        $this->assertFalse($row['claimed']);
        $this->assertNotNull($row['waiting_handle_age_human']);
        $this->assertStringContainsString('Marked paid', (string) $row['created']);
        $this->assertStringContainsString('unclaimed', (string) $row['created']);
        $this->assertSame('no_funds_signal', $row['payment_signal']);
        $this->assertArrayNotHasKey('auto_status', $row);
        $this->assertSame(3, (int) $task->status);
    }
}

/** Minimal Task-like object for mapper tests (no DB). */
final class TaskStub
{
    public int $id = 1001;

    public int $public_id = 1001;

    public int $status = 3;

    public int $id_direction_exchange = 1;

    public string $created_at = '2026-10-16 10:00:00';

    public string $updated_at = '2026-10-16 12:00:00';

    public string $started_at = '2026-10-16 12:00:00';

    public int $in_flow_funds = 0;

    public int $register_tx = 0;

    public string $give_price = '10';

    public string $receiving_price = '10';

    public $direction_exchange = null;

    public $task_info = null;

    /** @var array<int, object> */
    public array $task_operators = [];

    public static function unclaimedWaitingHandle(): self
    {
        return new self;
    }
}
