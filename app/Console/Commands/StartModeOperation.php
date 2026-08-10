<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\StatusSiteEvent;
use App\Services\Rates\AtomicPublicXmlPublisher;
use App\Services\Rates\RatesXmlMonitorGate;
use App\Support\WorkStatusFresh;
use iEXPackages\Courses\CoursesFacade;
use iEXPackages\WorkStatus\Services\WorkStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fail-closed resume:
 * build+validate candidate feed BEFORE clearing pause / going online.
 */
final class StartModeOperation extends Command
{
    protected $signature = 'operation:start';

    protected $description = 'Запуск рабочего режима операторов';

    public function handle(WorkStatusService $workStatus): int
    {
        $T0 = microtime(true);
        WorkStatusFresh::forgetRequestCache();
        $this->info('operation:start begin fail_closed=1');

        $livePath = public_path('static/exports/currencies.xml');
        $candidatePath = $livePath.'.candidate';

        // 1) Rebuild candidate feed WHILE still paused (public empty feed unchanged).
        $publish = $this->call('scheme:files', [
            '--force-publish' => true,
        ]);
        if ($publish !== self::SUCCESS) {
            $this->failClosed('scheme_files_force_publish_failed');

            return self::FAILURE;
        }

        // 2) Validate + rate-integrity on CANDIDATE before any public promote.
        $sourcePath = is_file($candidatePath) ? $candidatePath : $livePath;
        $validation = $this->validatePublishedFeed($sourcePath);
        if (!$validation['ok']) {
            $this->failClosed('feed_validation_failed:'.$validation['reason']);

            return self::FAILURE;
        }

        $rateGate = \App\Services\Rates\RateFeedResumeGate::evaluate($sourcePath);
        if (!$rateGate['ok']) {
            $this->failClosed('rate_integrity_gate:'.($rateGate['reason'] ?? 'failed'));

            return self::FAILURE;
        }

        // 3) Atomically promote candidate → live (still offline / tech on).
        if (is_file($candidatePath)) {
            $promoted = $this->promoteCandidates();
            if (!$promoted['ok']) {
                $this->failClosed('candidate_promote_failed:'.$promoted['reason']);

                return self::FAILURE;
            }
        }

        $liveCheck = $this->validatePublishedFeed($livePath);
        if (!$liveCheck['ok']) {
            $this->failClosed('live_after_promote_invalid:'.$liveCheck['reason']);

            return self::FAILURE;
        }

        // 4) Immediate online transition — minimize post-promote offline window.
        WorkStatusFresh::applyOperatorOnline($workStatus, 'Готов к приёму заявок');

        if (is_file(RatesXmlMonitorGate::FLAG_PATH)) {
            @unlink(RatesXmlMonitorGate::FLAG_PATH);
        }
        Cache::forget('exchanger_client:tech_status_v1');
        WorkStatusFresh::forgetRequestCache();

        // 5) Restore currenciesbest (non-fatal; after online so it cannot delay gate).
        RatesXmlMonitorGate::restoreCurrenciesBestFromParser();

        if ((int) iEXSetting('is_enabled_module_socket', 0) === 1) {
            broadcast(new StatusSiteEvent(' готов к приему заявок', 1));
        }

        // Refresh direction snapshots only — never re-run XML clear via stale offline.
        $this->call('scheme:files', ['--skip-export' => true]);

        if (WorkStatusFresh::isOffline()) {
            $this->failClosed('status_still_offline_after_apply');

            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $T0, 3);
        Log::info('operation_start_ok', [
            'items' => $liveCheck['items'],
            'elapsed_sec' => $elapsed,
            'xml' => $livePath,
            'mode' => WorkStatusFresh::hasActiveSchedules() ? 'schedule_force_online' : 'manual_online',
            'fresh_offline' => WorkStatusFresh::isOffline() ? 1 : 0,
        ]);
        $this->info("operation:start OK items={$liveCheck['items']} elapsed_sec={$elapsed}");

        return self::SUCCESS;
    }

    /**
     * @return array{ok:bool,reason:?string}
     */
    private function promoteCandidates(): array
    {
        $publisher = new AtomicPublicXmlPublisher();
        $map = [
            public_path('static/exports/currencies.xml'),
            public_path('static/exports/changed-currencies.xml'),
        ];

        foreach ($map as $live) {
            $candidate = $live.'.candidate';
            if (!is_file($candidate)) {
                continue;
            }
            $xml = (string) file_get_contents($candidate);
            $isCanonical = str_ends_with($live, 'currencies.xml')
                && str_contains($live, '/static/exports/');
            $result = $publisher->publish($live, $xml, [
                'min_items' => 1,
                'backup' => true,
                'sync_legacy' => $isCanonical,
            ]);
            if (!$result['published']) {
                return ['ok' => false, 'reason' => ($result['reason'] ?? 'publish_refused').':'.$live];
            }
            @unlink($candidate);
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return array{ok:bool,reason:?string,items:int}
     */
    private function validatePublishedFeed(string $path): array
    {
        if (!is_file($path) || filesize($path) < 50) {
            return ['ok' => false, 'reason' => 'missing_or_tiny', 'items' => 0];
        }
        $xml = (string) file_get_contents($path);
        if (!str_contains($xml, '<rates') || !str_contains($xml, '</rates>')) {
            return ['ok' => false, 'reason' => 'missing_rates_root', 'items' => 0];
        }
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            return ['ok' => false, 'reason' => 'xml_parse_error', 'items' => 0];
        }
        $items = substr_count($xml, '<item>');
        if ($items < 1) {
            return ['ok' => false, 'reason' => 'zero_items', 'items' => 0];
        }

        return ['ok' => true, 'reason' => null, 'items' => $items];
    }

    private function failClosed(string $reason): void
    {
        Log::error('operation_start_fail_closed', ['reason' => $reason]);
        $this->error('operation:start FAIL_CLOSED reason='.$reason);
        foreach ([
            public_path('static/exports/currencies.xml.candidate'),
            public_path('static/exports/changed-currencies.xml.candidate'),
        ] as $cand) {
            if (is_file($cand)) {
                @unlink($cand);
            }
        }
        RatesXmlMonitorGate::hide('operation:start_fail_closed:'.$reason);
        try {
            CoursesFacade::export($this)->store(isClear: true);
        } catch (\Throwable $e) {
            Log::error('operation_start_fail_closed_clear_failed', ['message' => $e->getMessage()]);
        }
        Cache::forget('exchanger_client:tech_status_v1');
        WorkStatusFresh::forgetRequestCache();
    }
}
