<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Export\Concerns;

use App\Models\ExportRatesFile;
use iEXPackages\Courses\Export\ExportFormatFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Трейт DefaultExport обеспечивает логику генерации файлов с курсами.
 *
 * Главная цель оптимизации:
 * - Экспорт не должен собирать весь файл в память.
 * - Экспорт должен обрабатывать направления чанками и писать в файл потоково.
 */
trait DefaultExport
{
    private int $isOperatorStatus = 0;

    /**
     * Выполняет процесс экспорта курсов в файлы на основе конфигураций.
     */
    protected function loadingDefault(): void
    {
        $this->isOperatorStatus = work_is_offline() ? 1 : 0;

        // cursor() вместо get(): не грузим все конфиги в память
        foreach (ExportRatesFile::query()->where('status', 1)->cursor() as $fileConfig) {
            $this->resetExportState();

            $safeFilename = $this->sanitizeFilename((string) ($fileConfig->filename ?? ''));
            if ($safeFilename === null) {
                Log::error("Некорректное имя файла для конфигурации ID: {$fileConfig->id}");
                continue;
            }

            try {
                $format = ExportFormatFactory::make((int) $fileConfig->type_file);
            } catch (\InvalidArgumentException $e) {
                Log::error($e->getMessage());
                continue;
            }

            $filename = public_path(sprintf('/static/exports/%s.%s', $safeFilename, $format->getExtension()));

            $this->ensureDirectoryExists(dirname($filename));

            if ($this->isClear) {
                try {
                    $this->clear($filename);
                } catch (Throwable $e) {
                    Log::error("Ошибка очистки файла ({$filename}): {$e->getMessage()}");
                }
                continue;
            }

            $builder = $this->builder();

            if (!empty($fileConfig->ids_excluded_directions)) {
                $builder->whereNotIn('id', $fileConfig->ids_excluded_directions);
            }

            try {
                /**
                 * Если формат поддерживает потоковую сборку — используем её.
                 * Это не ломает структуру XML, но радикально снижает память.
                 *
                 * Формат сам решает, как писать (XMLWriter / JSON stream).
                 */
                if (method_exists($format, 'assembleToFile')) {
                    $format->assembleToFile($builder, $fileConfig, $filename);
                    continue;
                }

                // Fallback: старый путь (assemble возвращает строку)
                $fileData = $format->assemble($builder, $fileConfig);
                $this->put($filename, $fileData);

            } catch (Throwable $e) {
                Log::error("Ошибка записи файла курсов ({$filename}): {$e->getMessage()}");
            }
        }
    }

    /**
     * Сбрасывает состояние перед каждой новой обработкой файла.
     *
     * Важно:
     * - Если экспорт переведён на streaming, items/data не должны накапливаться,
     *   но оставляем сброс для совместимости со старым assemble().
     */
    private function resetExportState(): void
    {
        $this->countTotalUpdate = 0;
        $this->countUpdateData = 0;
        $this->items = [];
        $this->data = [];
    }

    private function sanitizeFilename(?string $filename): ?string
    {
        $safeFilename = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $filename);
        return $safeFilename !== '' ? $safeFilename : null;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (File::exists($directory)) {
            return;
        }

        if (File::makeDirectory($directory, 0755, true)) {
            // Можно убрать лог совсем или оставить только в local
            // Log::info("Создана директория для экспорта: {$directory}");
        }
    }
}
