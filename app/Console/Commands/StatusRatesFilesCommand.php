<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HistoryUpdatedData;
use App\Services\Rates\RatesXmlMonitorGate;
use App\Services\Rates\RatesXmlPublicFeedHealth;
use App\Services\Rates\SchemeFilesScheduledSkipGate;
use App\Support\WorkStatusFresh;
use iEXPackages\Courses\CoursesFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StatusRatesFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheme:files
        {--force-publish : Rebuild currencies.xml even while exchanger is offline (used by fail-closed operation:start)}
        {--skip-export : Update direction snapshots only; do not touch XML export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update courses files and exchange directions';

    /**
     * Same path as ops scoped_locks.sh EXS_LOCK_SCHEME_FILES.
     * Manual --force-publish (StartModeOperation) bypasses run_backend_compiler.sh,
     * so this process must take the same flock or fail closed — never race a
     * scheduled/post-bc export that already holds scheme-files.lock.
     */
    public const SCHEME_FILES_LOCK_PATH = '/var/lock/build/scheme-files.lock';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gate = app(SchemeFilesScheduledSkipGate::class);
        $xmlPath = public_path('static/exports/currencies.xml');
        $forcePublish = (bool) $this->option('force-publish');

        // Force-publish coordinates with scheduled/post-bc via scheme-files.lock.
        // Scheduled ticks already hold that lock in the compiler wrapper — do not
        // re-acquire here (child would always see LOCK_NB fail).
        $sfLock = false;
        if ($forcePublish) {
            $sfLock = $this->acquireSchemeFilesLock();
            if ($sfLock === false) {
                Log::warning('scheme_files_force_publish_scheme_lock_busy', [
                    'path' => self::SCHEME_FILES_LOCK_PATH,
                ]);
                $this->error('scheme-files.lock busy — force-publish refused (fail closed).');
                $gate->recordFailure('force_publish_scheme_lock_busy');

                return self::FAILURE;
            }
        }

        try {
            $decision = $gate->evaluate([
                'invocation' => SchemeFilesScheduledSkipGate::processInvocation(),
                'force_publish' => $forcePublish,
                'skip_export' => (bool) $this->option('skip-export'),
                'xml_path' => $xmlPath,
            ]);

            if ($decision['skip'] === true) {
                $this->info('scheme:files scheduled skip — complete inputs unchanged ('.$decision['reason'].')');
                Log::info('scheme_files_scheduled_skip', [
                    'reason' => $decision['reason'],
                    'fingerprint' => $decision['fingerprint'],
                ]);
                // History row keeps ops visibility without implying a full rebuild cost.
                $this->logHistory('export_files', 0, 0, 0.0);
                $this->logHistory('export_exchange', 0, 0, 0.0);

                return self::SUCCESS;
            }

            if ($decision['enabled'] === true) {
                Log::info('scheme_files_scheduled_skip_bypass', [
                    'reason' => $decision['reason'],
                ]);
            }

            $exportOk = true;
            if (! $this->option('skip-export')) {
                $ok = $this->updateCoursesFile();
                if ($ok === false) {
                    $gate->recordFailure('xml_export_failed');

                    return self::FAILURE;
                }
                $exportOk = $ok !== false;
            }

            $directionsOk = $this->updateExchangeDirections();

            if ($exportOk && $directionsOk) {
                try {
                    $fp = $decision['fingerprint'] ?? $gate->computeInputFingerprint();
                    $xmlMeta = [];
                    if (is_file($xmlPath)) {
                        $xml = (string) file_get_contents($xmlPath);
                        $xmlMeta = [
                            'xml_sha256' => hash('sha256', $xml),
                            'xml_items' => substr_count($xml, '<item>'),
                            'xml_bytes' => strlen($xml),
                            'dr_snapshot_id' => Cache::get('dr_snapshot_id'),
                        ];
                    }
                    $gate->recordSuccess((string) $fp, $xmlMeta);
                } catch (\Throwable $e) {
                    // Fail-open for next tick: do not leave a false success meta.
                    $gate->recordFailure('success_meta_failed:'.$e->getMessage());
                    Log::warning('scheme_files_skip_meta_record_failed', [
                        'message' => $e->getMessage(),
                    ]);
                }
            } else {
                $gate->recordFailure($directionsOk ? 'export_partial' : 'directions_failed');
            }

            return self::SUCCESS;
        } finally {
            $this->releaseSchemeFilesLock($sfLock);
        }
    }

    /**
     * Обновление файла курсов.
     *
     * @return bool|null null when skipped path not used; false on hard failure for force-publish
     */
    private function updateCoursesFile(): ?bool
    {
        $this->comment('- Запуск обновления файла курсов');

        $start = microtime(true);
        $forcePublish = (bool) $this->option('force-publish');

        $lockFh = $this->acquireXmlPublishLock();
        if ($lockFh === false) {
            Log::warning('scheme_files_xml_publish_lock_busy', [
                'path' => RatesXmlPublicFeedHealth::PUBLISH_LOCK_PATH,
                'force_publish' => $forcePublish,
            ]);
            if ($forcePublish) {
                // operation:start must not report success without publishing.
                $this->error('XML publish lock busy — force-publish refused (fail closed).');

                return false;
            }
            $this->warn('XML publish lock busy — skipping export this tick (last-good remains).');

            return true;
        }

        try {
            $export = CoursesFacade::export($this);

            // Canonical pause authority: RatesXmlMonitorGate + FRESH offline status.
            // Never use work_is_offline() here — inside Artisan::call() from admin HTTP
            // it can be frozen offline by WorkStatusMiddleware and wipe a just-started feed.
            if ($forcePublish) {
                try {
                    $export->store(isClear: false);
                } finally {
                    // force-publish is resume-path: drop any leftover monitor hide flag.
                    RatesXmlMonitorGate::show('scheme:files_force_publish');
                    RatesXmlPublicFeedHealth::assertOnlineInvariant('scheme:files_force_publish');
                }
            } else {
                $isClear = WorkStatusFresh::isOffline();
                if ($isClear) {
                    RatesXmlMonitorGate::hide('scheme:files_offline');
                    $export->store(isClear: true);
                } else {
                    // Online ticks: clear sticky hide flag BEFORE rebuild so put()
                    // publishes to the live path (not .candidate).
                    if (RatesXmlMonitorGate::isHidden()) {
                        RatesXmlMonitorGate::show('scheme:files_online');
                    }
                    try {
                        $export->store(isClear: false);
                    } finally {
                        // Guaranteed restoration: concurrent clear/hide cannot leave
                        // a sticky flag while the site remains online.
                        if (RatesXmlMonitorGate::isHidden() && ! WorkStatusFresh::isOffline()) {
                            RatesXmlMonitorGate::show('scheme:files_online_finally');
                        }
                        RatesXmlPublicFeedHealth::assertOnlineInvariant('scheme:files_online_post');
                    }
                }
            }

            $time = round(microtime(true) - $start, 4);
            $this->info("+ Файл курсов успешно обновлен (за {$time} сек.)");

            $this->logHistory('export_files', $export->getCountUpdateData(), $export->getCountTotalUpdate(), $time);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении файла курсов: '.$exception->getMessage());
            $this->error('Произошла ошибка при обновлении файла курсов.');
            // Generator failure: never empty the public feed; keep last-good.
            RatesXmlPublicFeedHealth::assertOnlineInvariant('scheme:files_exception');

            return $forcePublish ? false : true;
        } finally {
            $this->releaseXmlPublishLock($lockFh);
        }
    }

    /**
     * @return resource|false
     */
    private function acquireXmlPublishLock()
    {
        $path = RatesXmlPublicFeedHealth::PUBLISH_LOCK_PATH;
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return false;
        }
        // Non-blocking: overlapping ticks keep last-good rather than racing.
        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return false;
        }

        return $fh;
    }

    /**
     * @param  resource|false  $fh
     */
    private function releaseXmlPublishLock($fh): void
    {
        if ($fh === false) {
            return;
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /**
     * @return resource|false
     */
    private function acquireSchemeFilesLock()
    {
        $path = self::SCHEME_FILES_LOCK_PATH;
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return false;
        }
        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return false;
        }

        return $fh;
    }

    /**
     * @param  resource|false  $fh
     */
    private function releaseSchemeFilesLock($fh): void
    {
        if ($fh === false) {
            return;
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /**
     * Обновление направлений обмена.
     */
    private function updateExchangeDirections(): bool
    {
        $this->comment('');
        $this->comment('---------------------');
        $this->comment('Запускаем процесс обновления направлений...');

        $start = microtime(true);

        try {
            $rates = CoursesFacade::withData([])->setCommand($this);

            foreach ($rates->availableLanguage() as $locale => $value) {
                $rates->setLocale($locale)->builder();
                $this->info('Информация обновлена для '.strtoupper($locale));
            }

            Cache::increment('dr_snapshot_id');

            $time = round(microtime(true) - $start, 4);
            $this->comment('Направления успешно обновлены');
            $this->info("Время обновления направлений: {$time} сек.");

            $this->logHistory('export_exchange', $rates->getCountUpdateData(), $rates->getCountTotalUpdate(), $time);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении направлений: '.$exception->getMessage());
            $this->error('Произошла ошибка при обновлении направлений.');

            return false;
        } finally {
            $this->comment('---------------------');
        }
    }

    /**
     * Логирование истории обновлений.
     */
    private function logHistory(string $type, int $updatedCount, int $totalCount, float $time): void
    {
        $history = HistoryUpdatedData::where('type_update', $type)->latest()->first();

        HistoryUpdatedData::create([
            'time' => $time,
            'type_update' => $type,
            'count_num' => $updatedCount,
            'total_num' => $totalCount,
            'old_time' => $history->time ?? 0,
        ]);
    }
}
