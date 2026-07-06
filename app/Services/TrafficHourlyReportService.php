<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TrafficHourlyReportService
{
    private const TELEGRAM_TEXT_MAX = 4096;

    /** @var list<string> */
    private const PERCENT_DELTA_METRICS = [
        'active_visitor_identities',
        'activity_hits',
        'api_hits',
        'orders_created',
    ];

    /** @var list<string> */
    private const ABSOLUTE_DELTA_METRICS = [
        'support_conversations',
        'support_visitor_messages',
    ];

    public function __construct(
        private readonly TrafficHourlyAnalyticsService $analytics,
        private readonly TrafficNginxLogRollupService $nginxRollup,
    ) {}

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodForHours(int $hours): array
    {
        return $this->analytics->periodForHours($hours);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultPeriod(): array
    {
        return $this->analytics->defaultPeriod();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReportData(
        ?CarbonImmutable $periodStart = null,
        ?CarbonImmutable $periodEnd = null,
    ): array {
        if ($periodStart === null || $periodEnd === null) {
            [$periodStart, $periodEnd] = $this->defaultPeriod();
        }

        $current = $this->analytics->aggregate($periodStart, $periodEnd);
        $periodHours = max(1, (int) ($current['period_hours'] ?? 1));
        $previousStart = $periodStart->subHours($periodHours);
        $previous = $this->analytics->aggregate($previousStart, $periodStart);

        $current['previous_period_label'] = $previous['period_label'];
        $current['previous'] = $this->extractMetrics($previous);
        $current['presence_health'] = $this->analytics->presenceHealthSnapshot();
        $current['alerts'] = $this->evaluateAlerts($current, $previous);
        $current['nginx'] = $this->nginxRollup->rollup($periodStart, $periodEnd);

        return $current;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function formatTelegramText(array $data): string
    {
        $previous = is_array($data['previous'] ?? null) ? $data['previous'] : [];

        $lines = [
            '📊 Exswaping site activity',
            '',
            '👥 Active visitor identities: '.$this->formatMetricWithDelta(
                $data,
                $previous,
                'active_visitor_identities',
                'percent',
            ),
            '📡 Activity hits: '.$this->formatMetricWithDelta(
                $data,
                $previous,
                'activity_hits',
                'percent',
            ),
            '🔌 API hits: '.$this->formatMetricWithDelta(
                $data,
                $previous,
                'api_hits',
                'percent',
            ),
            '🧾 Orders created: '.$this->formatMetricWithDelta(
                $data,
                $previous,
                'orders_created',
                'percent',
            ),
            '💬 Support conversations: '.$this->formatMetricWithDelta(
                $data,
                $previous,
                'support_conversations',
                'absolute',
            ),
            '📨 Support visitor messages: '.$this->formatMetricWithDelta(
                $data,
                $previous,
                'support_visitor_messages',
                'absolute',
            ),
            '',
        ];

        $lines = array_merge($lines, $this->formatNginxSections(is_array($data['nginx'] ?? null) ? $data['nginx'] : []));

        $alerts = is_array($data['alerts'] ?? null) ? $data['alerts'] : [];
        if ($alerts === []) {
            $lines[] = '✅ No alerts for this period.';
        } else {
            $lines[] = '⚠️ Alerts:';
            foreach ($alerts as $alert) {
                $lines[] = '• '.$alert;
            }
        }

        $lines[] = '';
        $lines[] = 'Period: '.($data['period_label'] ?? '—').' ('.($data['timezone'] ?? 'UTC').')';
        $lines[] = 'Compared with: '.($data['previous_period_label'] ?? '—');
        $lines[] = 'Scope: Site traffic estimate from existing session/activity data + nginx log rollup.';

        $concurrentMax = $data['concurrent_max'] ?? null;
        if ($concurrentMax !== null && (int) $concurrentMax > 0) {
            $periodHours = max(1, (int) ($data['period_hours'] ?? 1));
            $peakLabel = $periodHours > 1 ? 'peak concurrent identities in period' : 'peak concurrent identities this hour';
            $lines[] = 'Estimate: '.$peakLabel.' ≈ '.(int) $concurrentMax.'.';
        }

        $text = implode("\n", $lines);

        if (mb_strlen($text, 'UTF-8') > self::TELEGRAM_TEXT_MAX) {
            $text = mb_substr($text, 0, self::TELEGRAM_TEXT_MAX - 1, 'UTF-8').'…';
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $nginx
     * @return list<string>
     */
    private function formatNginxSections(array $nginx): array
    {
        if (($nginx['enabled'] ?? false) !== true || ($nginx['available'] ?? false) !== true) {
            return [];
        }

        $lines = [];

        $countries = is_array($nginx['countries'] ?? null) ? $nginx['countries'] : [];
        if ($countries !== []) {
            $lines[] = '🌍 Countries:';
            foreach ($countries as $country) {
                if (! is_array($country)) {
                    continue;
                }
                $lines[] = ($country['flag'] ?? '🏳️').' '.($country['name'] ?? 'Unknown').' — '.(int) ($country['count'] ?? 0);
            }
            if (is_string($nginx['country_note'] ?? null) && $nginx['country_note'] !== '') {
                $lines[] = $nginx['country_note'];
            }
            $lines[] = '';
        }

        $locales = is_array($nginx['locales'] ?? null) ? $nginx['locales'] : [];
        if ($locales !== []) {
            $localeParts = [];
            foreach ($locales as $locale) {
                if (! is_array($locale)) {
                    continue;
                }
                $localeParts[] = ($locale['code'] ?? 'unknown').' — '.(int) ($locale['percent'] ?? 0).'%';
            }
            if ($localeParts !== []) {
                $lines[] = '🗣 Languages:';
                $lines[] = implode("\n", $localeParts);
                $lines[] = '';
            }
        }

        $topPages = is_array($nginx['top_pages'] ?? null) ? $nginx['top_pages'] : [];
        if ($topPages !== []) {
            $lines[] = '🔥 Top pages:';
            $lines[] = '';
            $rank = 1;
            foreach ($topPages as $page) {
                if (! is_array($page)) {
                    continue;
                }
                $lines[] = $rank.'. '.($page['path'] ?? '/').' — '.(int) ($page['count'] ?? 0);
                $rank++;
            }
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendTelegramReport(array $data): bool
    {
        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            Log::warning('traffic hourly-report: telegram disabled');

            return false;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = trim((string) config('support_chat.telegram.hourly_report.chat_id', ''));

        if ($token === '' || $chatId === '') {
            Log::warning('traffic hourly-report: missing bot token or chat id');

            return false;
        }

        $text = $this->formatTelegramText($data);

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (Throwable $e) {
            Log::warning('traffic hourly-report: transport_error', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('traffic hourly-report: http_error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $payload = $response->json();
        if (! is_array($payload) || empty($payload['ok'])) {
            Log::warning('traffic hourly-report: api_rejected', [
                'response' => $payload,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, int|null>
     */
    private function extractMetrics(array $current): array
    {
        $metrics = array_merge(self::PERCENT_DELTA_METRICS, self::ABSOLUTE_DELTA_METRICS);
        $extracted = [];

        foreach ($metrics as $metric) {
            $extracted[$metric] = $this->metricAsInt($current[$metric] ?? null);
        }

        return $extracted;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return list<string>
     */
    private function evaluateAlerts(array $current, array $previous): array
    {
        $alerts = [];

        $visitors = $this->metricAsInt($current['active_visitor_identities'] ?? null);
        $orders = $this->metricAsInt($current['orders_created'] ?? null);
        $prevVisitors = $this->metricAsInt($previous['active_visitor_identities'] ?? null);
        $apiHits = $this->metricAsInt($current['api_hits'] ?? null);
        $prevApiHits = $this->metricAsInt($previous['api_hits'] ?? null);
        $supportMessages = $this->metricAsInt($current['support_visitor_messages'] ?? null);
        $prevSupportMessages = $this->metricAsInt($previous['support_visitor_messages'] ?? null);
        $activityHits = $this->metricAsInt($current['activity_hits'] ?? null);

        $minVisitors = (int) config('traffic.report.alerts.high_traffic_zero_orders_min_visitors', 100);
        $visitorDropPercent = (int) config('traffic.report.alerts.visitor_drop_percent', 50);
        $supportSpikeMinDelta = (int) config('traffic.report.alerts.support_spike_min_delta', 5);
        $apiDropPercent = (int) config('traffic.report.alerts.api_drop_percent', 60);

        if ($visitors !== null && $orders !== null && $visitors >= $minVisitors && $orders === 0) {
            $alerts[] = 'High traffic but zero orders';
        }

        if ($visitors !== null && $prevVisitors !== null && $prevVisitors > 0) {
            $dropPercent = (($prevVisitors - $visitors) / $prevVisitors) * 100;
            if ($dropPercent >= $visitorDropPercent) {
                $alerts[] = 'Visitor activity dropped significantly';
            }
        }

        if ($supportMessages !== null && $prevSupportMessages !== null) {
            $supportDelta = $supportMessages - $prevSupportMessages;
            if ($supportDelta >= $supportSpikeMinDelta) {
                $alerts[] = 'Support messages increased';
            }
        }

        if ($apiHits !== null && $prevApiHits !== null && $prevApiHits > 0 && $visitors !== null && $visitors > 0) {
            $apiDrop = (($prevApiHits - $apiHits) / $prevApiHits) * 100;
            if ($apiDrop >= $apiDropPercent) {
                $alerts[] = 'API hits dropped sharply while visitors remain active';
            }
        }

        $presenceHealth = is_array($current['presence_health'] ?? null) ? $current['presence_health'] : [];
        $presenceStale = ! empty($presenceHealth['presence_tracking_stale']);
        $minApiHitsForStaleWarning = max(1, (int) config('traffic.report.presence.min_api_hits_for_stale_warning', 100));

        if ($presenceStale || (
            $activityHits === 0
            && $apiHits !== null
            && $apiHits >= $minApiHitsForStaleWarning
            && ($visitors === 0 || $visitors === null)
        )) {
            $alerts[] = 'Presence tracking appears stale; API traffic is still being recorded, but live visitor/activity counts may be incomplete.';
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, int|null>  $previous
     */
    private function formatMetricWithDelta(array $current, array $previous, string $key, string $mode): string
    {
        $value = $this->formatMetric($current[$key] ?? null);
        $currentInt = $this->metricAsInt($current[$key] ?? null);
        $previousInt = $previous[$key] ?? null;

        if ($currentInt === null && $previousInt === null) {
            return $value;
        }

        $delta = $mode === 'percent'
            ? $this->formatPercentDelta($currentInt, $previousInt)
            : $this->formatAbsoluteDelta($currentInt, $previousInt);

        return $value.$delta;
    }

    private function formatPercentDelta(?int $current, ?int $previous): string
    {
        if ($current === null || $previous === null) {
            return '';
        }

        if ($previous === 0 && $current === 0) {
            return ' (—)';
        }

        if ($previous === 0 && $current > 0) {
            return ' (new)';
        }

        $percent = (int) round((($current - $previous) / $previous) * 100);

        if ($percent === 0) {
            return ' (0%)';
        }

        return $percent > 0 ? ' (+'.$percent.'%)' : ' ('.$percent.'%)';
    }

    private function formatAbsoluteDelta(?int $current, ?int $previous): string
    {
        if ($current === null || $previous === null) {
            return '';
        }

        if ($previous === 0 && $current === 0) {
            return ' (—)';
        }

        if ($previous === 0 && $current > 0) {
            return ' (new)';
        }

        $delta = $current - $previous;

        if ($delta === 0) {
            return ' (0)';
        }

        return $delta > 0 ? ' (+'.$delta.')' : ' ('.$delta.')';
    }

    private function formatMetric(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return (string) (int) $value;
    }

    private function metricAsInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
