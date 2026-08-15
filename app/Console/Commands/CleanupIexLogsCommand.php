<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class CleanupIexLogsCommand extends Command
{
    protected $signature = 'logs:cleanup
        {--dry : Показать, что будет удалено, без удаления}';

    protected $description = 'Удаляет старые iex-лог файлы и любые логи больше 300 МБ в storage/logs';

    /** Порог размера для удаления безусловно (в байтах). */
    private const SIZE_THRESHOLD = 300 * 1024 * 1024; // 300 MB

    public function handle(): int
    {
        $logsPath = storage_path('logs');

        if (! is_dir($logsPath)) {
            $this->warn("Папка логов не найдена: {$logsPath}");
            return self::SUCCESS;
        }

        $dryRun = (bool)$this->option('dry');
        $today  = Carbon::today(); // берёт таймзону приложения
        $keepDates = [
            $today->copy()->subDays(0)->toDateString(),
            $today->copy()->subDays(1)->toDateString(),
            $today->copy()->subDays(2)->toDateString(),
        ];

        $deleted = 0;
        $totalSizeFreed = 0;

        // Собираем все файлы в storage/logs (только файлы верхнего уровня)
        $finder = (new Finder())
            ->files()
            ->in($logsPath)
            ->depth('== 0');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $filename = $file->getFilename();
            $size     = $file->getSize();

            // 1) Удаляем любой лог > 300 МБ (безусловно)
            if ($size !== false && $size >= self::SIZE_THRESHOLD) {
                $this->line("Большой файл (>300MB): {$filename} (" . human_bytes($size) . ")");
                if (! $dryRun) {
                    if (@unlink($filePath)) {
                        $deleted++;
                        $totalSizeFreed += $size;
                        $this->info("Удалён: {$filename}");
                    } else {
                        $this->error("Не удалось удалить: {$filename}");
                    }
                }
                // Перейти к следующему файлу
                continue;
            }

            // 2) Обрабатываем только iex-дневники формата: iex-YYYY-MM-DD[.log|.gz] (или без расширения)
            // Допустимы варианты: iex-2025-10-13, iex-2025-10-13.log, iex-2025-10-13.gz, iex-2025-10-13.log.gz
            $dateStr = $this->extractIexDate($filename);

            if ($dateStr === null) {
                // Это не наш iex-лог — пропускаем (но уже удалили бы по размеру, если нужно)
                continue;
            }

            // Если дата файла входит в последние 3 дня — сохраняем
            if (in_array($dateStr, $keepDates, true)) {
                continue;
            }

            // Иначе — удаляем
            $this->line("Старый iex-лог: {$filename} (дата: {$dateStr})");
            if (! $dryRun) {
                $freed = $size ?: 0;
                if (@unlink($filePath)) {
                    $deleted++;
                    $totalSizeFreed += $freed;
                    $this->info("Удалён: {$filename}");
                } else {
                    $this->error("Не удалось удалить: {$filename}");
                }
            }
        }

        if ($dryRun) {
            $this->comment('DRY-RUN: ничего не удалено.');
        } else {
            $this->info("Готово. Удалено файлов: {$deleted}. Освобождено: " . human_bytes($totalSizeFreed) . ".");
        }

        return self::SUCCESS;
    }

    /**
     * Извлекает дату из имени файла iex-логов.
     * Поддерживаемые примеры:
     *  - iex-2025-10-13
     *  - iex-2025-10-13.log
     *  - iex-2025-10-13.gz
     *  - iex-2025-10-13.log.gz
     */
    private function extractIexDate(string $filename): ?string
    {
        // Убираем известные расширения
        $name = $filename;
        foreach (['.log', '.gz'] as $ext) {
            if (Str::endsWith($name, $ext)) {
                $name = Str::beforeLast($name, $ext);
            }
        }

        // Должно остаться iex-YYYY-MM-DD
        if (preg_match('/^iex-(\d{4}-\d{2}-\d{2})$/', $name, $m)) {
            return $m[1];
        }

        return null;
    }
}
