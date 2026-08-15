<?php

namespace App\Console\Commands;

use App\Models\OrderExport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearOldExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exports:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удаляет экспорт-файлы и записи, срок действия которых истёк (старше 72 часов)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Очистка устаревших экспортов...');

        // Выборка всех экспортов старше 72 часов
        $expiredExports = OrderExport::where('finished_at', '<=', Carbon::now()->subDays(3))
            ->whereNotNull('file_name')
            ->get();

        if ($expiredExports->isEmpty()) {
            $this->info('Нет устаревших экспортов для удаления.');
            return Command::SUCCESS;
        }

        $disk = Storage::disk('local');

        foreach ($expiredExports as $export) {
            $file = $export->file_name;

            // Проверяем файл в корне
            if ($disk->exists($file)) {
                $disk->delete($file);
                $this->info("Удалён файл: $file");
            }

            // Проверяем файл в /exports/
            $path = "exports/$file";
            if ($disk->exists($path)) {
                $disk->delete($path);
                $this->info("Удалён файл: $path");
            }

            // Удаляем запись в БД
            $export->delete();
            $this->info("Удалена запись экспорта ID: {$export->id}");
        }

        $this->info('Очистка завершена.');
        return Command::SUCCESS;
    }
}
