<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Privacy-safe site activity estimates from existing production tables (read-only aggregates).
 */
final class TrafficHourlyAnalyticsService
{
    public const SCOPE_SITE_ESTIMATE = 'site_traffic_estimate';

    /**
     * @return array{
     *     scope: string,
     *     period_start: string,
     *     period_end: string,
     *     period_label: string,
     *     period_hours: int,
     *     timezone: string,
     *     active_visitor_identities: int|null,
     *     activity_hits: int|null,
     *     concurrent_max: int|null,
     *     api_hits: int|null,
     *     orders_created: int|null,
     *     support_conversations: int|null,
     *     support_visitor_messages: int|null
     * }
     */
    public function aggregate(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $localStart = $periodStart->timezone($tz);
        $localEnd = $periodEnd->timezone($tz);

        $rangeStart = $localStart->format('Y-m-d H:i:s');
        $rangeEnd = $localEnd->format('Y-m-d H:i:s');
        $displayEnd = $localEnd->subSecond();

        return [
            'scope' => self::SCOPE_SITE_ESTIMATE,
            'period_start' => $localStart->toIso8601String(),
            'period_end' => $displayEnd->toIso8601String(),
            'period_label' => $this->formatPeriodLabel($localStart, $displayEnd),
            'period_hours' => max(1, (int) $localStart->diffInHours($localEnd)),
            'timezone' => $tz,
            'active_visitor_identities' => $this->countActiveVisitorIdentities($rangeStart, $rangeEnd),
            'activity_hits' => $this->sumActivityHitsInRange($localStart, $localEnd),
            'concurrent_max' => $this->peakConcurrentInRange($localStart, $localEnd),
            'api_hits' => $this->sumApiHitsInRange($localStart, $localEnd),
            'orders_created' => $this->countOrdersCreated($rangeStart, $rangeEnd),
            'support_conversations' => $this->countSupportConversations($rangeStart, $rangeEnd),
            'support_visitor_messages' => $this->countSupportVisitorMessages($rangeStart, $rangeEnd),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultPeriod(): array
    {
        return $this->periodForHours(1);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodForHours(int $hours): array
    {
        $hours = max(1, $hours);
        $tz = (string) config('app.timezone', 'UTC');
        $end = CarbonImmutable::now($tz)->startOfHour();
        $start = $end->subHours($hours);

        return [$start, $end];
    }

    private function formatPeriodLabel(CarbonImmutable $localStart, CarbonImmutable $displayEnd): string
    {
        if ($localStart->toDateString() === $displayEnd->toDateString()) {
            return $localStart->format('H:i').'–'.$displayEnd->format('H:i');
        }

        return $localStart->format('Y-m-d H:i').'–'.$displayEnd->format('Y-m-d H:i');
    }

    private function sumActivityHitsInRange(CarbonImmutable $localStart, CarbonImmutable $localEnd): ?int
    {
        if (! Schema::hasTable('online_hourly_stats')) {
            return null;
        }

        $total = 0;
        $cursor = $localStart->startOfHour();
        $limit = $localEnd->startOfHour();

        while ($cursor->lt($limit)) {
            $total += $this->sumActivityHits($cursor->format('Y-m-d H:00:00')) ?? 0;
            $cursor = $cursor->addHour();
        }

        return $total;
    }

    private function peakConcurrentInRange(CarbonImmutable $localStart, CarbonImmutable $localEnd): ?int
    {
        if (! Schema::hasTable('online_hourly_stats')) {
            return null;
        }

        $peak = 0;
        $cursor = $localStart->startOfHour();
        $limit = $localEnd->startOfHour();

        while ($cursor->lt($limit)) {
            $peak = max($peak, $this->peakConcurrent($cursor->format('Y-m-d H:00:00')) ?? 0);
            $cursor = $cursor->addHour();
        }

        return $peak;
    }

    private function sumApiHitsInRange(CarbonImmutable $localStart, CarbonImmutable $localEnd): ?int
    {
        if (! Schema::hasTable('visit_counters_hour')) {
            return null;
        }

        $total = 0;
        $cursor = $localStart->startOfHour();
        $limit = $localEnd->startOfHour();

        while ($cursor->lt($limit)) {
            $total += $this->sumApiHits($cursor->utc()->format('Y-m-d H:00:00')) ?? 0;
            $cursor = $cursor->addHour();
        }

        return $total;
    }

    private function countActiveVisitorIdentities(string $rangeStart, string $rangeEnd): ?int
    {
        if (! Schema::hasTable('online_sessions')) {
            return null;
        }

        return (int) DB::table('online_sessions')
            ->where('last_seen', '>=', $rangeStart)
            ->where('last_seen', '<', $rangeEnd)
            ->distinct()
            ->count('identity');
    }

    private function sumActivityHits(string $hourBucketLocal): ?int
    {
        if (! Schema::hasTable('online_hourly_stats')) {
            return null;
        }

        $row = DB::table('online_hourly_stats')
            ->where('hour', $hourBucketLocal)
            ->first(['auth_hits', 'guest_hits']);

        if ($row === null) {
            return 0;
        }

        return (int) ($row->auth_hits ?? 0) + (int) ($row->guest_hits ?? 0);
    }

    private function peakConcurrent(string $hourBucketLocal): ?int
    {
        if (! Schema::hasTable('online_hourly_stats')) {
            return null;
        }

        $value = DB::table('online_hourly_stats')
            ->where('hour', $hourBucketLocal)
            ->value('concurrent_max');

        return $value === null ? 0 : (int) $value;
    }

    private function sumApiHits(string $hourBucketUtc): ?int
    {
        if (! Schema::hasTable('visit_counters_hour')) {
            return null;
        }

        $value = DB::table('visit_counters_hour')
            ->where('bucket_hour', $hourBucketUtc)
            ->value('total');

        return $value === null ? 0 : (int) $value;
    }

    private function countOrdersCreated(string $rangeStart, string $rangeEnd): ?int
    {
        if (! Schema::hasTable('tasks')) {
            return null;
        }

        return (int) Task::query()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->count();
    }

    private function countSupportConversations(string $rangeStart, string $rangeEnd): ?int
    {
        if (! Schema::hasTable('support_conversations')) {
            return null;
        }

        return (int) SupportConversation::query()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->count();
    }

    private function countSupportVisitorMessages(string $rangeStart, string $rangeEnd): ?int
    {
        if (! Schema::hasTable('support_messages')) {
            return null;
        }

        return (int) SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->count();
    }

    /**
     * Read-only presence pipeline health for traffic report warnings.
     *
     * @return array{
     *     online_sessions_max_last_seen: string|null,
     *     online_sessions_stale_minutes: int|null,
     *     presence_queue_depth: int|null,
     *     presence_tracking_stale: bool
     * }
     */
    public function presenceHealthSnapshot(): array
    {
        $snapshot = [
            'online_sessions_max_last_seen' => null,
            'online_sessions_stale_minutes' => null,
            'presence_queue_depth' => null,
            'presence_tracking_stale' => false,
        ];

        $staleMinutesThreshold = max(1, (int) config('traffic.report.presence.stale_minutes', 15));
        $backlogThreshold = max(1, (int) config('traffic.report.presence.queue_backlog_threshold', 1000));

        if (Schema::hasTable('online_sessions')) {
            $maxLastSeen = DB::table('online_sessions')->max('last_seen');
            if (is_string($maxLastSeen) && $maxLastSeen !== '') {
                $snapshot['online_sessions_max_last_seen'] = $maxLastSeen;
                $snapshot['online_sessions_stale_minutes'] = (int) CarbonImmutable::parse($maxLastSeen)->diffInMinutes(CarbonImmutable::now());
            }
        }

        try {
            $snapshot['presence_queue_depth'] = (int) Redis::connection()->llen('queues:presence');
        } catch (Throwable) {
            // Redis unavailable — leave depth null; stale check uses online_sessions only.
        }

        $sessionsStale = $snapshot['online_sessions_stale_minutes'] !== null
            && $snapshot['online_sessions_stale_minutes'] > $staleMinutesThreshold;
        $backlogHigh = $snapshot['presence_queue_depth'] !== null
            && $snapshot['presence_queue_depth'] > $backlogThreshold;

        $snapshot['presence_tracking_stale'] = $sessionsStale || $backlogHigh;

        return $snapshot;
    }
}
