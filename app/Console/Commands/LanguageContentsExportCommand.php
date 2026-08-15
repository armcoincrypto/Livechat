<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class LanguageContentsExportCommand extends Command
{
    protected $signature = 'language:export
        {--id=1 : ID строки language_contents (обычно 1)}
        {--path= : Путь сохранения (по умолчанию storage/app/exports/language_contents.json)}';

    protected $description = 'Экспортировать language_contents в JSON-файл (для миграции в DynamicConfig).';

    public function handle(): int
    {
        if (!Schema::hasTable('language_contents')) {
            $this->error('Таблица language_contents не найдена.');
            return self::FAILURE;
        }

        $id = (int) $this->option('id');

        $row = DB::table('language_contents')->where('id', $id)->first();

        if (!$row) {
            $this->error("Строка language_contents.id={$id} не найдена.");
            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: storage_path('app/exports/language_contents.json'));
        $dir  = dirname($path);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'table'       => 'language_contents',
            'row_id'      => $id,
            'data'        => (array) $row,
        ];

        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info("✅ Экспорт сохранён: {$path}");
        return self::SUCCESS;
    }
}
