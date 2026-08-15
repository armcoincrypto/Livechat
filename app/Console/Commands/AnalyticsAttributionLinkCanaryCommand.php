<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\AttributionFeatures;
use App\Services\Analytics\AttributionOrderLinker;
use App\Services\Analytics\AttributionSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Non-financial C.3D link canary.
 *
 * Inserts a synthetic session_attributions row, links a duck-typed fake task
 * via AttributionOrderLinker (real DB lookup), then deletes the synthetic row.
 * Never creates orders, never moves funds, never touches payments.
 */
final class AnalyticsAttributionLinkCanaryCommand extends Command
{
    protected $signature = 'analytics:attribution-link-canary
        {--keep-flag : Do not restore EXS_ATTRIBUTION_ORDER_LINK_ENABLED after run}';

    protected $description = 'Synthetic C.3D order-link canary (no real orders / no funds)';

    public function handle(AttributionOrderLinker $linker, AttributionSanitizer $sanitizer): int
    {
        $repo = Env::getRepository();
        $prevLink = $repo->get('EXS_ATTRIBUTION_ORDER_LINK_ENABLED');
        $prevEvents = $repo->get('EXS_ATTRIBUTION_EVENTS_ENABLED');

        $repo->set('EXS_ATTRIBUTION_ORDER_LINK_ENABLED', 'true');
        $repo->set('EXS_ATTRIBUTION_EVENTS_ENABLED', 'false');

        $sid = 'stagec_c3d'.Str::lower(Str::random(20));
        $now = Carbon::now();
        $attrId = null;

        try {
            $this->line('CANARY_SESSION='.$sid);
            $this->line('EVENTS_ENABLED='.(AttributionFeatures::eventsEnabled() ? 'true' : 'false'));
            $this->line('ORDER_LINK_ENABLED='.(AttributionFeatures::orderLinkEnabled() ? 'true' : 'false'));

            // Collection path (ManagerOrder shape).
            $fromCollection = $linker->sessionIdFromOptions(collect(['public_session_id' => $sid, 'session_attribution_id' => 999]));
            $this->line('COLLECTION_OPTIONS_OK='.($fromCollection === $sid ? 'yes' : 'no'));

            $attrId = (int) DB::table('session_attributions')->insertGetId([
                'public_session_id' => $sid,
                'first_utm_source' => 'canary',
                'first_utm_medium' => 'c3d',
                'first_utm_campaign' => 'link-canary',
                'last_utm_source' => 'canary',
                'last_utm_medium' => 'c3d',
                'last_utm_campaign' => 'link-canary',
                'first_landing_path' => '/en/',
                'last_landing_path' => '/en/',
                'locale' => 'en',
                'device_class' => 'desktop',
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $task = new class
            {
                public $id = 900001;

                public $session_attribution_id = null;

                public bool $saved = false;

                public function forceFill(array $attrs): self
                {
                    foreach ($attrs as $k => $v) {
                        $this->{$k} = $v;
                    }

                    return $this;
                }

                public function saveQuietly(): bool
                {
                    $this->saved = true;

                    return true;
                }
            };

            $linker->attachFailOpen($task, $sid);
            $linked = (int) $task->session_attribution_id === $attrId && $task->saved;
            $this->line('VALID_LINK='.($linked ? 'yes' : 'no'));
            $this->line('POINTER='.json_encode($task->session_attribution_id));

            // Unknown id → NULL pointer on fresh task.
            $task2 = new class
            {
                public $id = 900002;

                public $session_attribution_id = null;

                public bool $saved = false;

                public function forceFill(array $attrs): self
                {
                    foreach ($attrs as $k => $v) {
                        $this->{$k} = $v;
                    }

                    return $this;
                }

                public function saveQuietly(): bool
                {
                    $this->saved = true;

                    return true;
                }
            };
            $linker->attachFailOpen($task2, 'unknownsessionidabcdef12');
            $this->line('UNKNOWN_NULL='.($task2->session_attribution_id === null && ! $task2->saved ? 'yes' : 'no'));

            // Malformed → NULL.
            $linker->attachFailOpen($task2, 'bad!!!');
            $this->line('MALFORMED_NULL='.($task2->session_attribution_id === null ? 'yes' : 'no'));

            // Idempotent re-link.
            $before = $task->session_attribution_id;
            $linker->attachFailOpen($task, $sid);
            $this->line('IDEMPOTENT='.($task->session_attribution_id === $before ? 'yes' : 'no'));

            // Flag off → no link.
            $repo->set('EXS_ATTRIBUTION_ORDER_LINK_ENABLED', 'false');
            $task3 = new class
            {
                public $id = 900003;

                public $session_attribution_id = null;

                public bool $saved = false;

                public function forceFill(array $attrs): self
                {
                    foreach ($attrs as $k => $v) {
                        $this->{$k} = $v;
                    }

                    return $this;
                }

                public function saveQuietly(): bool
                {
                    $this->saved = true;

                    return true;
                }
            };
            $linker->attachFailOpen($task3, $sid);
            $this->line('FLAG_OFF_NULL='.($task3->session_attribution_id === null && ! $task3->saved ? 'yes' : 'no'));

            $this->info('REAL_ORDERS=0 FUNDS_MOVED=0 PAYMENTS=0 TASK_ROWS_WRITTEN=0');
            $ok = $linked
                && $fromCollection === $sid
                && $task2->session_attribution_id === null
                && $task3->session_attribution_id === null
                && ! AttributionFeatures::eventsEnabled();

            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('canary_failed class='.$e::class.' msg='.$e->getMessage());

            return self::FAILURE;
        } finally {
            if ($attrId) {
                DB::table('session_attributions')->where('id', $attrId)->delete();
            }
            DB::table('session_attributions')->where('public_session_id', $sid)->delete();

            if (! $this->option('keep-flag')) {
                $repo->set('EXS_ATTRIBUTION_ORDER_LINK_ENABLED', $prevLink ?? 'false');
                $repo->set('EXS_ATTRIBUTION_EVENTS_ENABLED', $prevEvents ?? 'false');
            }
        }
    }
}
