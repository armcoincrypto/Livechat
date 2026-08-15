<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final class MigrateFromJsonCommand extends Command
{
    protected $signature = 'dynamic-config:migrate-from-json
                            {path? : Путь к iex-config.json (по умолчанию storage/app/iex-config.json)}
                            {--scope=global : Тип scope}
                            {--scope-id= : ID scope (по умолчанию null)}
                            {--mode=missing : missing|if-changed|replace}
                            {--force : Принудительно перезаписать все ключи (эквивалент mode=replace)}
                            {--export : Backup JSON перед переносом}
                            {--dry-run : Только показать, что будет сделано, без записи в БД}';

    protected $description = 'Синхронизация настроек из iex-config.json в DynamicConfig (создание/обновление по режиму).';

    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?? storage_path('app/iex-config.json'));

        if (!File::exists($path)) {
            $this->error("Файл не найден: {$path}");
            return self::FAILURE;
        }

        if ($this->option('export')) {
            $this->exportJsonBackup($path);
        }

        // JSON
        try {
            $raw  = File::get($path);
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->error('Ошибка чтения или парсинга iex-config.json');
            Log::error('DynamicConfig migrate-from-json parse error', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }

        if (!is_array($data)) {
            $this->error('Некорректный JSON: ожидался объект с ключами и значениями.');
            return self::FAILURE;
        }

        // Scope
        $scopeType = strtolower((string) $this->option('scope'));
        $scopeId   = $this->option('scope-id') !== null && $this->option('scope-id') !== ''
            ? (int) $this->option('scope-id')
            : null;

        $scope = Scope::fromString($scopeType, $scopeId);

        $mode = strtolower((string) $this->option('mode'));
        if (!in_array($mode, ['missing', 'if-changed', 'replace'], true)) {
            $this->error('Неверный --mode. Допустимо: missing|if-changed|replace');
            return self::FAILURE;
        }

        if ((bool) $this->option('force')) {
            $mode = 'replace';
        }

        $dryRun = (bool) $this->option('dry-run');

        // Загружаем все существующие ключи для scope (одним запросом)
        $existingRows = DB::table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->whereNull('deleted_at')
            ->get(['key', 'value']);

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(string) $row->key] = (string) $row->value; // raw JSON-string
        }

        $toInsert = 0;
        $toUpdate = 0;
        $skipped  = 0;

        $now = now();

        DB::beginTransaction();

        try {
            foreach ($data as $key => $value) {
                $key = (string) $key;

                // Нормализуем значения из старого json:
                // если строка содержит JSON ("[]" / "{}") — распарсим в массив.
                $value = $this->normalizeJsonValue($value);

                $newJson = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                $exists = array_key_exists($key, $existingMap);

                // missing: только вставка отсутствующих
                if ($mode === 'missing') {
                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $toInsert++;

                    if (!$dryRun) {
                        DB::table('dynamic_config_settings')->insert([
                            'scope_type' => $scope->type,
                            'scope_id'   => $scope->id,
                            'key'        => $key,
                            'value'      => $newJson,
                            'expires_at' => null,
                            'deleted_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    continue;
                }

                // replace: вставить если нет / обновить если есть
                if ($mode === 'replace') {
                    if ($exists) {
                        $toUpdate++;
                    } else {
                        $toInsert++;
                    }

                    if (!$dryRun) {
                        DB::table('dynamic_config_settings')->updateOrInsert(
                            [
                                'scope_type' => $scope->type,
                                'scope_id'   => $scope->id,
                                'key'        => $key,
                            ],
                            [
                                'value'      => $newJson,
                                'expires_at' => null,
                                'deleted_at' => null,
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
                    }

                    continue;
                }

                // if-changed: вставить если нет, обновить только если отличается
                if ($mode === 'if-changed') {
                    if (!$exists) {
                        $toInsert++;

                        if (!$dryRun) {
                            DB::table('dynamic_config_settings')->insert([
                                'scope_type' => $scope->type,
                                'scope_id'   => $scope->id,
                                'key'        => $key,
                                'value'      => $newJson,
                                'expires_at' => null,
                                'deleted_at' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        continue;
                    }

                    // сравниваем сырой JSON из БД и новый JSON
                    $oldJson = $existingMap[$key];

                    if ($this->jsonEquals($oldJson, $newJson)) {
                        $skipped++;
                        continue;
                    }

                    $toUpdate++;

                    if (!$dryRun) {
                        DB::table('dynamic_config_settings')
                            ->where('scope_type', $scope->type)
                            ->where('scope_id', $scope->id)
                            ->where('key', $key)
                            ->update([
                                'value'      => $newJson,
                                'expires_at' => null,
                                'deleted_at' => null,
                                'updated_at' => $now,
                            ]);
                    }

                    continue;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error('Ошибка синхронизации настроек в DynamicConfig.');
            Log::error('DynamicConfig migrate-from-json sync error', [
                'scope_type' => $scope->type,
                'scope_id'   => $scope->id,
                'mode'       => $mode,
                'error'      => $e->getMessage(),
            ]);
            return self::FAILURE;
        }

        $this->info('Синхронизация завершена.');
        $this->line('Файл: ' . $path);
        $this->line('Scope: ' . $scope->type . ' / ' . ($scope->id === null ? 'null' : (string) $scope->id));
        $this->line('Режим: ' . $mode . ($dryRun ? ' (dry-run)' : ''));
        $this->line('Будет вставлено: ' . $toInsert);
        $this->line('Будет обновлено: ' . $toUpdate);
        $this->line('Пропущено: ' . $skipped);

        return self::SUCCESS;
    }

    private function exportJsonBackup(string $jsonPath): void
    {
        try {
            $dir = storage_path('app/exports');
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $ts = date('Ymd_His');
            $out = $dir . '/iex-config-backup-' . $ts . '.json';

            File::put($out, File::get($jsonPath));

            $this->info('Backup JSON сохранён: ' . $out);
        } catch (\Throwable $e) {
            $this->warn('Не удалось сделать backup JSON: ' . $e->getMessage());
        }
    }

    /**
     * Если строка выглядит как JSON ("[]" / "{}") — распарсить.
     */
    private function normalizeJsonValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trim = trim($value);
        if ($trim === '') {
            return $value;
        }

        $first = $trim[0] ?? '';
        if ($first !== '{' && $first !== '[') {
            return $value;
        }

        try {
            return json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Сравнение двух JSON-строк по смыслу (а не по тексту).
     */
    private function jsonEquals(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        try {
            $da = json_decode($a, true, 512, JSON_THROW_ON_ERROR);
            $db = json_decode($b, true, 512, JSON_THROW_ON_ERROR);

            return $da === $db;
        } catch (\Throwable) {
            // если что-то не JSON, сравниваем как строки
            return $a === $b;
        }
    }
}
