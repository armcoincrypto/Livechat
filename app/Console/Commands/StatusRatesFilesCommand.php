<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HistoryUpdatedData;
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
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('skip-export')) {
            $ok = $this->updateCoursesFile();
            if ($ok === false) {
                return self::FAILURE;
            }
        }
        $this->updateExchangeDirections();

        return self::SUCCESS;
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

        try {
            $export = CoursesFacade::export($this);

            // Canonical pause authority: RatesXmlMonitorGate + FRESH offline status.
            // Never use work_is_offline() here — inside Artisan::call() from admin HTTP
            // it can be frozen offline by WorkStatusMiddleware and wipe a just-started feed.
            if ($forcePublish) {
                $export->store(isClear: false);
            } else {
                $isClear = WorkStatusFresh::isOffline();
                if ($isClear) {
                    \App\Services\Rates\RatesXmlMonitorGate::hide('scheme:files_offline');
                } else {
                    // Online ticks: ensure hide flag is absent; do not restore parser dump every 2m.
                    if (\App\Services\Rates\RatesXmlMonitorGate::isHidden()) {
                        @unlink(\App\Services\Rates\RatesXmlMonitorGate::FLAG_PATH);
                    }
                }
                $export->store(isClear: $isClear);
            }

            $time = round(microtime(true) - $start, 4);
            $this->info("+ Файл курсов успешно обновлен (за {$time} сек.)");

            $this->logHistory('export_files', $export->getCountUpdateData(), $export->getCountTotalUpdate(), $time);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении файла курсов: ' . $exception->getMessage());
            $this->error('Произошла ошибка при обновлении файла курсов.');

            return $forcePublish ? false : true;
        }
    }

    /**
     * Обновление направлений обмена.
     */
    private function updateExchangeDirections(): void
    {
        $this->comment('');
        $this->comment('---------------------');
        $this->comment('Запускаем процесс обновления направлений...');

        $start = microtime(true);

        try {
            $rates = CoursesFacade::withData([])->setCommand($this);

            foreach ($rates->availableLanguage() as $locale => $value) {
                $rates->setLocale($locale)->builder();
                $this->info("Информация обновлена для " . strtoupper($locale));
            }

            Cache::increment('dr_snapshot_id');

            $time = round(microtime(true) - $start, 4);
            $this->comment('Направления успешно обновлены');
            $this->info("Время обновления направлений: {$time} сек.");

            $this->logHistory('export_exchange', $rates->getCountUpdateData(), $rates->getCountTotalUpdate(), $time);

        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении направлений: ' . $exception->getMessage());
            $this->error('Произошла ошибка при обновлении направлений.');
        }

        $this->comment('---------------------');
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
