<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HistoryUpdatedData;
use App\Services\Rates\BestChangeRubRecoveryPackage;
use App\Services\Rates\IndependentMarketBaseline;
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
    protected $signature = 'scheme:files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update courses files and exchange directions';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $snapshot = IndependentMarketBaseline::beginSnapshot(purpose: 'export');
        Cache::put('rates:last_export_snapshot', $snapshot, now()->addDay());

        try {
            if (!$this->updateCoursesFile()) {
                throw new \RuntimeException('Canonical XML export failed; package generation aborted');
            }
            $this->updateExchangeDirections();
            app(BestChangeRubRecoveryPackage::class)->generate();
        } finally {
            IndependentMarketBaseline::endSnapshot();
        }
    }

    /**
     * Обновление файла курсов.
     */
    private function updateCoursesFile(): bool
    {
        $this->comment('- Запуск обновления файла курсов');

        $start = microtime(true);

        try {
            $export = CoursesFacade::export($this);

            $isClear = work_is_offline();
            $export->store(isClear: $isClear);

            $time = round(microtime(true) - $start, 4);
            $this->info("+ Файл курсов успешно обновлен (за {$time} сек.)");

            $this->logHistory('export_files', $export->getCountUpdateData(), $export->getCountTotalUpdate(), $time);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Ошибка при обновлении файла курсов: ' . $exception->getMessage());
            $this->error('Произошла ошибка при обновлении файла курсов.');

            return false;
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
