<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return function () {
//    Artisan::call('migrate', [
//        '--path' => 'database/migrations/updates/10.0.1',
//        '--force' => true,
//    ]);



    Schema::dropIfExists('competitor_rates_log');
    Schema::dropIfExists('parser_formula_logs');

    loadingMasterKey();
    loadingPulseDB();
};


function loadingMasterKey()
{
    $envPath = base_path('.env');
    $keyName = 'VAULT_MASTER_KEY';

    if (!file_exists($envPath)) {
        Log::error(".env файл не найден: $envPath");
        return;
    }

    $envContent = file_get_contents($envPath);

    // Если ключ существует, но пустой — обновим
    if (preg_match("/^{$keyName}=(.*)$/m", $envContent, $matches)) {
        $value = trim($matches[1]);
        if (!empty($value)) {
            return;
        }

        $newValue = Str::random(32);
        $envContent = preg_replace("/^{$keyName}=.*$/m", "{$keyName}={$newValue}", $envContent);
        file_put_contents($envPath, $envContent);
        Log::info("VAULT_MASTER_KEY был пустым и теперь установлен.");
        return;
    }

    // Ключа нет — добавим в конец файла
    $newValue = Str::random(32);
    $envContent .= PHP_EOL . "{$keyName}={$newValue}" . PHP_EOL;
    file_put_contents($envPath, $envContent);
    Log::info("VAULT_MASTER_KEY добавлен в .env.");
}


function loadingPulseDB()
{
    $connection = 'mysql-pulse';
    $sqlFile = database_path('pulse_db.sql');

    // Проверка: если хотя бы одна таблица существует — ничего не делаем
    $tables = ['pulse_aggregates', 'pulse_entries', 'pulse_values'];
    $existing = collect($tables)->some(fn($table) => Schema::connection($connection)->hasTable($table));

    if ($existing) {
        return; // просто выходим, ничего не делаем
    }

// Проверка наличия SQL-файла
    if (!file_exists($sqlFile)) {
        Log::error("SQL файл не найден: $sqlFile");
        return;
    }

    // Читаем SQL и выполняем
    $sql = file_get_contents($sqlFile);
    $db = DB::connection($connection);

    try {
        $db->transaction(function () use ($db, $sql) {
            $db->statement('SET FOREIGN_KEY_CHECKS=0');
            $db->unprepared($sql);
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        });
    } catch (\Exception $e) {
        Log::error('Ошибка при импорте Pulse SQL: ' . $e->getMessage());
    }
}
