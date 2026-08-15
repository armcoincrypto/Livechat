<?php

namespace App\Console\Commands;

use App\Jobs\UpdateBestchangeExchangeCodes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ApplyProductUpdateCommand extends Command
{
    protected $signature = 'product:apply-update';
    protected $description = 'Применяет пошаговые обновления продукта с миграциями и историей версий';

    public function handle(): int
    {

        $this->info("\n\u{1F501} Запуск применения обновлений...");

        $this->applyBootstrapUpdate();

        if (!Schema::hasTable('update_versions')) {
            $this->error('Таблица update_versions не найдена. Выполните bootstrap-обновление.');
            return Command::FAILURE;
        }

        $this->applyAllAvailableUpdates();

        $this->callSilent('optimize:clear');
        $this->info("\u{1F9F9} Кэш очищен.");
        $this->info("\u{2705} Обновление завершено успешно.");

        return Command::SUCCESS;
    }

    protected function applyBootstrapUpdate(): void
    {
        $bootstrapFile = database_path('updates/bootstrap/update.php');

        if (!File::exists($bootstrapFile)) {
            $this->line('⏭️ bootstrap/update.php отсутствует. Пропускаем.');
            return;
        }

        $callback = require $bootstrapFile;

        if (is_callable($callback)) {
            try {
                $callback();
                $this->info('✅ Bootstrap-обновление успешно применено.');
            } catch (\Throwable $e) {
                $this->error('❌ Ошибка в bootstrap-обновлении: ' . $e->getMessage());
            }
        } else {
            $this->warn('⚠️ Файл bootstrap/update.php не содержит допустимый callback.');
        }
    }

    protected function applyAllAvailableUpdates(): void
    {
        $updatesPath = database_path('updates');

        if (!File::exists($updatesPath)) {
            $this->warn("\u{26A0} Каталог обновлений не найден: $updatesPath");
            return;
        }

        $applied = DB::table('update_versions')->pluck('version')->toArray();

        $versions = collect(File::directories($updatesPath))
            ->map(fn($dir) => basename($dir))
            ->filter(fn($version) => $version !== 'bootstrap')
            ->sortBy(fn($version) => version_compare($version, '0.0.0', '>'))
            ->sort(fn($a, $b) => version_compare($a, $b))
            ->filter(fn($version) => !in_array($version, $applied));

        foreach ($versions as $version)
        {
            $this->info("\n\u{25B6} Применение версии $version...");
            $migrationsPath = File::exists(base_path("database/migrations/$version"))
                ? "database/migrations/$version"
                : (File::exists(base_path("database/updates/$version/migrations"))
                    ? "database/updates/$version/migrations"
                    : null);

            $updateFile = database_path("updates/$version/update.php");

            if (!File::exists($updateFile)) {
                $this->warn("\u{26A0} Файл update.php отсутствует для версии $version");
                continue;
            }

            $callback = require $updateFile;

            if (!is_callable($callback)) {
                $this->warn("\u{26A0} update.php для версии $version не содержит допустимый callable.");
                continue;
            }

            try {
                //DB::beginTransaction();

                if ($migrationsPath && File::exists(base_path($migrationsPath))) {
                    Artisan::call('migrate', [
                        '--path' => $migrationsPath,
                        '--force' => true,
                    ]);
                    $this->line(Artisan::output());
                }

                $callback();

                // Завершаем транзакцию ДО вставки записи о версии
               // DB::commit();


                DB::table('update_versions')->insert([
                    'version' => $version,
                    'applied_at' => now(),
                    'notes' => null,
                ]);

                File::put(base_path('.version'), $version);

                $this->info("Версия $version успешно применена.");
            } catch (\Throwable $e) {
//                if (DB::transactionLevel() > 0) {
//                    DB::rollBack();
//                }

                $this->error("Ошибка в версии $version: " . $e->getMessage());
            }
        }


        Artisan::call('migrate', [
            '--force' => true,
        ]);


        // Run dynamic-config:migrate after all updates
        try {
            Artisan::call('dynamic-config:migrate');
            $this->line(Artisan::output());
        } catch (\Throwable $throwable) {
            $this->warn('⚠️ Ошибка при выполнении dynamic-config:migrate: ' . $throwable->getMessage());
        }

        try {
            Artisan::call('bestchange:cache-update');

            // Теперь обновляем все коды BestChange
            UpdateBestchangeExchangeCodes::dispatch();

            Artisan::call('scheme:files');
        }catch (\Throwable $throwable) {
            //
        }
    }
}
