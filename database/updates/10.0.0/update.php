<?php

use App\Jobs\UpdateBestchangeExchangeCodes;
use App\Models\GroupParserExchange;
use App\Services\VaultService;
use Defuse\Crypto\Crypto;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return function () {
    migrateIexConfig();
    moveAmlGatewayVaultKeys();
    migrateVaultEncryption();
    relocateImagesByPrefix();


    $directories = [
        public_path('static'),
        public_path('static/exports')
    ];

    foreach ($directories as $directory) {
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0777, true, true);
        }
    }
};

function migrateIexConfig(): void
{
    $sourcePath = storage_path('iex-config.json');
    $destinationPath = storage_path('app/iex-config.json');

    // Проверка наличия файла источника
    if (!File::exists($sourcePath) || trim(File::get($sourcePath)) === '') {
        Log::warning("❌ Файл '$sourcePath' отсутствует или пуст — миграция отменена.");
        return;
    }

    // Проверка, существует ли уже файл назначения
    if (File::exists($destinationPath)) {
        Log::info("⚠️ Файл '$destinationPath' уже существует — миграция отменена.");
        return;
    }

    $content = trim(File::get($sourcePath));
    json_decode($content);
    $jsonError = json_last_error();

    if ($jsonError !== JSON_ERROR_NONE) {
        Log::warning("❌ Файл '$sourcePath' содержит повреждённый JSON — перенос отменён. Ошибка: " . json_last_error_msg());
        return;
    }

    if (!File::exists(dirname($destinationPath))) {
        File::makeDirectory(dirname($destinationPath), 0755, true);
    }

    File::copy($sourcePath, $destinationPath);

    $timestamp = now()->format('Y-m-d_H-i-s');
    $backupPath = storage_path("iex-config.json.$timestamp.bak");

    File::move($sourcePath, $backupPath);

    Log::info("✅ Файл '$sourcePath' успешно перенесён в '$destinationPath' и оригинал переименован в '$backupPath'.");
}



function relocateImagesByPrefix(): void
{
    $basePath = public_path('images');
    $directories = [
        'iex-bg-'    => public_path('images/backgrounds'),
        'logotype-'  => public_path('images/logotype'),
    ];

    foreach ($directories as $prefix => $destinationPath) {
        // Создание папки, если её нет
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        // Получаем список файлов по префиксу
        $files = collect(File::files($basePath))
            ->filter(fn($file) => str_starts_with($file->getFilename(), $prefix));

        // Проверка наличия файлов перед переносом
        if ($files->isEmpty()) {
            continue; // если файлов нет, просто пропускаем текущий префикс
        }

        foreach ($files as $file) {
            File::move($file->getRealPath(), $destinationPath . '/' . $file->getFilename());
        }
    }
}

function moveAmlGatewayVaultKeys(): void
{
    $transfers = [
        [
            'from' => storage_path('aml_services'),
            'to'   => config('vault.storage_path') . DIRECTORY_SEPARATOR . 'aml',
        ],
        [
            'from' => storage_path('gateways'),
            'to'   => config('vault.storage_path') . DIRECTORY_SEPARATOR . 'gateways',
        ],
    ];

    foreach ($transfers as $transfer) {
        $fromPath = $transfer['from'];
        $toPath = $transfer['to'];

        if (!$toPath) {
            Log::error("❌ Целевая папка не задана: $toPath");
            continue;
        }

        if (!File::exists($toPath)) {
            File::makeDirectory($toPath, 0755, true);
            Log::info("📁 Создана защищённая папка '$toPath'.");
        }

        if (!File::exists($fromPath)) {
            Log::warning("⚠️ Папка '$fromPath' не найдена. Перенос отменён.");
            continue;
        }

        $files = File::files($fromPath);

        if (empty($files)) {
            Log::info("ℹ️ Нет файлов для переноса из '$fromPath'. Папка '$toPath' всё равно создана.");
        } else {
            foreach ($files as $file) {
                $originalName = $file->getFilename();
                $filename = pathinfo($originalName, PATHINFO_FILENAME) . '.dat';
                $destination = $toPath . DIRECTORY_SEPARATOR . $filename;

                try {
                    File::move($file->getPathname(), $destination);
                    Log::info("🔑 Файл '$originalName' перенесён в '$toPath' как '$filename'.");
                } catch (\Throwable $e) {
                    Log::error("❌ Ошибка при переносе '$originalName': " . $e->getMessage());
                }
            }
        }

        try {
            File::deleteDirectory($fromPath);
            Log::info("🗑️ Папка '$fromPath' удалена после переноса (даже если она была пуста).");
        } catch (\Throwable $e) {
            Log::error("❌ Не удалось удалить '$fromPath': " . $e->getMessage());
        }
    }
}


function migrateVaultEncryption(): void
{
    $oldPassword = Config::get('iexexchanger.app_secret_password');
    $vaultRoot = rtrim(Config::get('vault.storage_path'), DIRECTORY_SEPARATOR);

    if (empty($oldPassword)) {
        logger()->error('⚠️ Старый пароль не задан в конфигурации.');
        return;
    }

    if (!File::exists($vaultRoot)) {
        logger()->error("⚠️ Папка не найдена: $vaultRoot");
        return;
    }

    $directories = collect(File::directories($vaultRoot))->push($vaultRoot);
    $vaultService = app(VaultService::class);
    $filesProcessed = 0;

    foreach ($directories as $directory) {
        $files = File::files($directory);

        if (empty($files)) {
            logger()->info("ℹ️ Папка пуста, пропускаем: " . basename($directory));
            continue;
        }

        foreach ($files as $file) {
            $relativePath = str_replace($vaultRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $subFolder = dirname($relativePath) !== '.' ? dirname($relativePath) : null;
            $filename = basename($relativePath);

            try {
                $encryptedContent = File::get($file->getPathname());
                $decryptedJson = Crypto::decryptWithPassword($encryptedContent, $oldPassword);
                $payload = json_decode($decryptedJson, true);

                if (!is_array($payload)) {
                    logger()->warning("⚠️ Старый формат данных в файле: $relativePath");
                    continue;
                }

                $data = $payload['data'] ?? $payload; // поддержка старого формата

                if (!is_array($data)) {
                    logger()->warning("⚠️ Невалидные данные в файле: $relativePath");
                    continue;
                }

                $vaultService->encryptToFile($filename, $data, $subFolder);

                logger()->info("✅ Успешно мигрирован: $relativePath");
                $filesProcessed++;

            } catch (Throwable $e) {
                logger()->error("❌ Ошибка миграции ($relativePath): " . $e->getMessage());
            }
        }
    }

    if ($filesProcessed === 0) {
        logger()->info('⚠️ Нет файлов для миграции.');
    } else {
        logger()->info("🚀 Миграция завершена. Обработано файлов: $filesProcessed");
    }
}
