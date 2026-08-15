<?php

use App\Models\GroupParserExchange;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return function () {

    if (!Schema::hasTable('update_versions')) {
        Schema::create('update_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->timestamp('applied_at')->useCurrent();
            $table->text('notes')->nullable();
        });
    }

    if((int)iEXSetting('max_decimal_places') == 0) {
        iEXSetting(['max_decimal_places' => 18]);
    }

    // Загружаем данные из JSON-файла
    $jsonData = json_decode(file_get_contents(storage_path('group_parsers.json')), true);

    // Получаем список alias из JSON в нижнем регистре
    $jsonAliases = collect($jsonData)->pluck('alias')->map(fn($alias) => strtolower($alias))->toArray();


    // Удаляем только те записи, которых нет в JSON-файле (с учётом регистра)
    GroupParserExchange::whereNotIn(DB::raw('LOWER(alias)'), $jsonAliases)->delete();

    // Обновляем или создаём записи (учитывая регистр)
    foreach ($jsonData as $value)
    {
        GroupParserExchange::updateOrCreate([
            'alias' => $value['alias']],
            [
                // 'id' => $value['id'],
                'name' => $value['name'],
                'alias' => $value['alias'],
                'provider_id' => $value['provider_id'],
            ]
        );
    }
    // Безопасное создание директории /static и файла custom-theme.css
    $staticPath = public_path('static');
    $customCssPath = $staticPath . '/custom-theme.css';

    // Создаем директорию /static, если она не существует
    if (!file_exists($staticPath)) {
        mkdir($staticPath, 0755, true);
    }

    // Создаем файл custom-theme.css, если он не существует
    if (!file_exists($customCssPath)) {
        file_put_contents($customCssPath, "/* Custom theme styles */\n");
    }

    ensurePublicDirectoriesFromTxt();


    $directories = [
        public_path('static'),
        public_path('static/exports')
    ];

    foreach ($directories as $directory) {
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0777, true, true);
        }
    }


    loadingMasterKey();
    loadingPulseDB();
};


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

/**
 * Проверяет папки из storage/app/public-directories.txt
 * и создаёт отсутствующие
 *
 * @param string $fileName имя txt файла в storage/app
 * @param int $permissions права для mkdir
 * @return array список созданных папок (public/...)
 */
function ensurePublicDirectoriesFromTxt(
    string $fileName = 'public-directories.txt',
    int $permissions = 0755
): array {
    if (!Storage::disk('local')->exists($fileName)) {
        throw new RuntimeException("Файл {$fileName} не найден");
    }

    $created = [];
    $basePath = base_path();

    $lines = preg_split(
        '/\R/',
        Storage::disk('local')->get($fileName),
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    foreach ($lines as $relativePath) {
        $relativePath = trim($relativePath);

        // защита: разрешаем ТОЛЬКО public/...
        if (!str_starts_with($relativePath, 'public/')) {
            continue;
        }

        $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, $permissions, true);
            $created[] = $relativePath;
        }
    }

    return $created;
}
