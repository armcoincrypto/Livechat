<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\AttributionEventRecorder;
use App\Services\Analytics\AttributionFeatures;
use App\Services\Analytics\AttributionFunnelEventMap;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Non-financial C.3E lifecycle-event canary.
 *
 * Uses synthetic session_attribution + synthetic task_id values (no Task row,
 * no payments, no funds). Exercises recorder + uniqueness + unlinked/fail-open.
 */
final class AnalyticsAttributionEventsCanaryCommand extends Command
{
    protected $signature = 'analytics:attribution-events-canary
        {--keep-flag : Do not restore EXS_ATTRIBUTION_EVENTS_ENABLED after run}';

    protected $description = 'Synthetic C.3E attribution lifecycle-event canary (no real orders / no funds)';

    public function handle(AttributionEventRecorder $recorder): int
    {
        $repo = Env::getRepository();
        $prevEvents = $repo->get('EXS_ATTRIBUTION_EVENTS_ENABLED');

        if (! DB::getSchemaBuilder()->hasTable('session_attribution_events')) {
            $this->error('session_attribution_events missing — run migrations first');

            return self::FAILURE;
        }

        $sid = 'stagec_c3e'.Str::lower(Str::random(20));
        $now = Carbon::now();
        $attrId = null;
        $taskBase = 910000000 + random_int(1000, 9999);
        $pass = true;

        try {
            $repo->set('EXS_ATTRIBUTION_EVENTS_ENABLED', 'true');
            $this->line('CANARY_SESSION='.$sid);
            $this->line('EVENTS_ENABLED='.(AttributionFeatures::eventsEnabled() ? 'true' : 'false'));
            $this->line('APPROVED_EVENTS='.implode(',', AttributionFunnelEventMap::approvedEventTypes()));

            $attrId = (int) DB::table('session_attributions')->insertGetId([
                'public_session_id' => $sid,
                'first_utm_source' => 'canary',
                'first_utm_medium' => 'c3e',
                'first_utm_campaign' => 'events-canary',
                'last_utm_source' => 'canary',
                'last_utm_medium' => 'c3e',
                'last_utm_campaign' => 'events-canary',
                'first_landing_path' => '/en/',
                'last_landing_path' => '/en/',
                'locale' => 'en',
                'device_class' => 'desktop',
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $linked = $this->fakeTask($taskBase, $attrId);

            // Happy path sequence
            foreach ([2, 7, 3, 4] as $status) {
                $recorder->recordStatusTransitionFailOpen($linked, $status);
            }
            // Duplicate transitions must not create extra rows
            foreach ([2, 7, 3, 4] as $status) {
                $recorder->recordStatusTransitionFailOpen($linked, $status);
            }

            $happy = DB::table('session_attribution_events')
                ->where('task_id', $taskBase)
                ->orderBy('id')
                ->pluck('event_type')
                ->all();
            $expectHappy = ['order_created', 'payment_detected', 'processing_started', 'order_completed'];
            $happyOk = $happy === $expectHappy;
            $this->line('HAPPY_SEQ='.($happyOk ? 'yes' : 'no').' got='.json_encode($happy));
            $pass = $pass && $happyOk;

            // Expired path (separate synthetic task)
            $expiredTask = $this->fakeTask($taskBase + 1, $attrId);
            $recorder->recordStatusTransitionFailOpen($expiredTask, 2);
            $recorder->recordStatusTransitionFailOpen($expiredTask, 1);
            $recorder->recordStatusTransitionFailOpen($expiredTask, 1);
            $expiredTypes = DB::table('session_attribution_events')
                ->where('task_id', $taskBase + 1)
                ->orderBy('id')
                ->pluck('event_type')
                ->all();
            $expiredOk = $expiredTypes === ['order_created', 'order_expired'];
            $this->line('EXPIRED_SEQ='.($expiredOk ? 'yes' : 'no').' got='.json_encode($expiredTypes));
            $pass = $pass && $expiredOk;

            // Cancelled path
            $cancelTask = $this->fakeTask($taskBase + 2, $attrId);
            $recorder->recordStatusTransitionFailOpen($cancelTask, 2);
            $recorder->recordStatusTransitionFailOpen($cancelTask, 6);
            $cancelTypes = DB::table('session_attribution_events')
                ->where('task_id', $taskBase + 2)
                ->orderBy('id')
                ->pluck('event_type')
                ->all();
            $cancelOk = $cancelTypes === ['order_created', 'order_cancelled'];
            $this->line('CANCEL_SEQ='.($cancelOk ? 'yes' : 'no').' got='.json_encode($cancelTypes));
            $pass = $pass && $cancelOk;

            // Autopay processing entry (16) shares processing_started; once only with 3
            $autoTask = $this->fakeTask($taskBase + 3, $attrId);
            $recorder->recordStatusTransitionFailOpen($autoTask, 2);
            $recorder->recordStatusTransitionFailOpen($autoTask, 7);
            $recorder->recordStatusTransitionFailOpen($autoTask, 16);
            $recorder->recordStatusTransitionFailOpen($autoTask, 3); // must not duplicate processing_started
            $autoTypes = DB::table('session_attribution_events')
                ->where('task_id', $taskBase + 3)
                ->orderBy('id')
                ->pluck('event_type')
                ->all();
            $autoOk = $autoTypes === ['order_created', 'payment_detected', 'processing_started'];
            $this->line('AUTOPAY_PROCESS_ONCE='.($autoOk ? 'yes' : 'no').' got='.json_encode($autoTypes));
            $pass = $pass && $autoOk;

            // REJECTED must not emit order_cancelled
            $rejTask = $this->fakeTask($taskBase + 4, $attrId);
            $recorder->recordStatusTransitionFailOpen($rejTask, 2);
            $recorder->recordStatusTransitionFailOpen($rejTask, 5);
            $rejCount = (int) DB::table('session_attribution_events')
                ->where('task_id', $taskBase + 4)
                ->where('event_type', 'order_cancelled')
                ->count();
            $this->line('REJECTED_NOT_CANCELLED='.($rejCount === 0 ? 'yes' : 'no'));
            $pass = $pass && $rejCount === 0;

            // Unlinked → no events
            $unlinked = $this->fakeTask($taskBase + 5, null);
            $before = (int) DB::table('session_attribution_events')->count();
            $recorder->recordStatusTransitionFailOpen($unlinked, 2);
            $recorder->recordStatusTransitionFailOpen($unlinked, 4);
            $after = (int) DB::table('session_attribution_events')->count();
            $this->line('UNLINKED_NO_EVENT='.(($after === $before) ? 'yes' : 'no'));
            $pass = $pass && $after === $before;

            // Flag off → no write
            $repo->set('EXS_ATTRIBUTION_EVENTS_ENABLED', 'false');
            $flagTask = $this->fakeTask($taskBase + 6, $attrId);
            $beforeFlag = (int) DB::table('session_attribution_events')->count();
            $recorder->recordStatusTransitionFailOpen($flagTask, 2);
            $afterFlag = (int) DB::table('session_attribution_events')->count();
            $this->line('FLAG_OFF_NO_EVENT='.(($afterFlag === $beforeFlag) ? 'yes' : 'no'));
            $pass = $pass && $afterFlag === $beforeFlag;

            // Fail-open: recorder must not throw even if task id weird — already covered by try/catch.
            $this->line('REAL_ORDERS=0 FUNDS_MOVED=0 PAYMENTS=0');
            $this->line($pass ? 'C3E_CANARY_PASS' : 'C3E_CANARY_FAIL');

            return $pass ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('canary exception: '.$e::class.' '.$e->getMessage());

            return self::FAILURE;
        } finally {
            // Cleanup synthetic rows
            DB::table('session_attribution_events')
                ->whereBetween('task_id', [$taskBase, $taskBase + 20])
                ->delete();
            if ($attrId) {
                DB::table('session_attributions')->where('id', $attrId)->delete();
            }
            if (! $this->option('keep-flag')) {
                if ($prevEvents === null) {
                    $repo->set('EXS_ATTRIBUTION_EVENTS_ENABLED', 'false');
                } else {
                    $repo->set('EXS_ATTRIBUTION_EVENTS_ENABLED', (string) $prevEvents);
                }
            }
        }
    }

    private function fakeTask(int $id, ?int $attrId): object
    {
        return new class($id, $attrId)
        {
            public function __construct(public $id, public $session_attribution_id)
            {
            }
        };
    }
}
