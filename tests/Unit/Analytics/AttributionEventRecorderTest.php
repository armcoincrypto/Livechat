<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\AttributionEventRecorder;
use App\Services\Analytics\AttributionFunnelEventMap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * C.3E unit/integration tests against the real events table when present.
 */
final class AttributionEventRecorderTest extends TestCase
{
    private function setEventsFlag(bool $on): void
    {
        Env::getRepository()->set('EXS_ATTRIBUTION_EVENTS_ENABLED', $on ? 'true' : 'false');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEventsFlag(false);
        if (! Schema::hasTable('session_attribution_events')) {
            Schema::create('session_attribution_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('session_attribution_id')->nullable();
                $table->unsignedBigInteger('task_id');
                $table->string('event_type', 32);
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['task_id', 'event_type']);
            });
        }
        DB::table('session_attribution_events')->where('task_id', '>=', 920000000)->delete();
    }

    protected function tearDown(): void
    {
        $this->setEventsFlag(false);
        if (Schema::hasTable('session_attribution_events')) {
            DB::table('session_attribution_events')->where('task_id', '>=', 920000000)->delete();
        }
        parent::tearDown();
    }

    public function test_flag_off_writes_nothing(): void
    {
        $this->setEventsFlag(false);
        $recorder = new AttributionEventRecorder();
        $recorder->recordStatusTransitionFailOpen($this->task(920000001, 1), 2);
        $this->assertSame(0, (int) DB::table('session_attribution_events')->where('task_id', 920000001)->count());
    }

    public function test_unlinked_writes_nothing(): void
    {
        $this->setEventsFlag(true);
        $recorder = new AttributionEventRecorder();
        $recorder->recordStatusTransitionFailOpen($this->task(920000002, null), 2);
        $this->assertSame(0, (int) DB::table('session_attribution_events')->where('task_id', 920000002)->count());
    }

    public function test_linked_happy_path_and_dedupe(): void
    {
        $this->setEventsFlag(true);
        $recorder = new AttributionEventRecorder();
        $task = $this->task(920000003, 55);
        foreach ([2, 7, 3, 4, 2, 7, 3, 4] as $status) {
            $recorder->recordStatusTransitionFailOpen($task, $status);
        }
        $types = DB::table('session_attribution_events')
            ->where('task_id', 920000003)
            ->orderBy('id')
            ->pluck('event_type')
            ->all();
        $this->assertSame(
            ['order_created', 'payment_detected', 'processing_started', 'order_completed'],
            $types
        );
    }

    public function test_expired_and_cancelled_paths(): void
    {
        $this->setEventsFlag(true);
        $recorder = new AttributionEventRecorder();
        $recorder->recordStatusTransitionFailOpen($this->task(920000004, 55), 2);
        $recorder->recordStatusTransitionFailOpen($this->task(920000004, 55), 1);
        $this->assertSame(
            ['order_created', 'order_expired'],
            DB::table('session_attribution_events')->where('task_id', 920000004)->orderBy('id')->pluck('event_type')->all()
        );

        $recorder->recordStatusTransitionFailOpen($this->task(920000005, 55), 2);
        $recorder->recordStatusTransitionFailOpen($this->task(920000005, 55), 6);
        $this->assertSame(
            ['order_created', 'order_cancelled'],
            DB::table('session_attribution_events')->where('task_id', 920000005)->orderBy('id')->pluck('event_type')->all()
        );
    }

    public function test_rejected_not_mapped_to_cancelled(): void
    {
        $this->assertNull(AttributionFunnelEventMap::eventForStatus(5));
        $this->assertContains(5, AttributionFunnelEventMap::UNMAPPED_TERMINAL);
        $this->setEventsFlag(true);
        $recorder = new AttributionEventRecorder();
        $recorder->recordStatusTransitionFailOpen($this->task(920000006, 55), 5);
        $this->assertSame(0, (int) DB::table('session_attribution_events')->where('task_id', 920000006)->count());
    }

    public function test_processing_started_once_across_waiting_handle_and_payout_queue(): void
    {
        $this->setEventsFlag(true);
        $recorder = new AttributionEventRecorder();
        $task = $this->task(920000007, 55);
        $recorder->recordStatusTransitionFailOpen($task, 16);
        $recorder->recordStatusTransitionFailOpen($task, 3);
        $this->assertSame(1, (int) DB::table('session_attribution_events')
            ->where('task_id', 920000007)
            ->where('event_type', 'processing_started')
            ->count());
    }

    public function test_db_failure_is_fail_open(): void
    {
        $this->setEventsFlag(true);
        $recorder = new AttributionEventRecorder();
        $broken = new class
        {
            public $id = 920000008;

            public $session_attribution_id = 55;
        };
        $recorder->recordStatusTransitionFailOpen($broken, 2);
        $this->assertTrue(true);
    }

    public function test_map_covers_required_events_only(): void
    {
        $approved = AttributionFunnelEventMap::approvedEventTypes();
        sort($approved);
        $this->assertSame(
            ['order_cancelled', 'order_completed', 'order_created', 'order_expired', 'payment_detected', 'processing_started'],
            $approved
        );
        $this->assertSame('payment_detected', AttributionFunnelEventMap::eventForStatus(7));
        $this->assertSame('processing_started', AttributionFunnelEventMap::eventForStatus(3));
        $this->assertSame('processing_started', AttributionFunnelEventMap::eventForStatus(16));
    }

    private function task(int $id, ?int $attrId): object
    {
        return new class($id, $attrId)
        {
            public function __construct(public $id, public $session_attribution_id)
            {
            }
        };
    }
}
