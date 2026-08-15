<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Storage;

use iEXPackages\DynamicConfig\Contracts\SettingsStorageInterface;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * Хранилище DynamicConfig в таблице dynamic_config_settings.
 *
 * Структура таблицы:
 *
 *  id         BIGINT UNSIGNED PK AUTO_INCREMENT
 *  scope_type VARCHAR(50)      NOT NULL
 *  scope_id   BIGINT UNSIGNED  NULL
 *  key        VARCHAR(191)     NOT NULL
 *  value      JSON             NOT NULL
 *  expires_at DATETIME         NULL
 *  deleted_at TIMESTAMP        NULL
 *  created_at TIMESTAMP        NULL
 *  updated_at TIMESTAMP        NULL
 */
final class DatabaseSettingsStorage implements SettingsStorageInterface
{
    /**
     * Подключение к БД, используемое для чтения/записи настроек.
     */
    private ConnectionInterface $connection;

    public function __construct(?ConnectionInterface $connection = null)
    {
        $this->connection = $connection ?? DB::connection();
    }

    /**
     * Загрузка всех настроек для указанного scope.
     *
     * Возвращает nested-массив:
     *
     *  [
     *      'group' => [
     *          'field' => value,
     *      ],
     *  ]
     *
     * где 'group.field' — это значение поля key в таблице.
     *
     * @param  Scope  $scope
     * @return array<string,mixed>
     */
    public function load(Scope $scope): array
    {
        $rows = $this->connection
            ->table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['key', 'value']);

        $settings = [];

        foreach ($rows as $row) {
            try {
                $decoded = $this->decodeValue((string) $row->value);
            } catch (\Throwable $e) {
                Log::error('DynamicConfig: ошибка декодирования значения', [
                    'key'   => $row->key,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            // Разворачиваем key "group.field" → $settings['group']['field']
            Arr::set($settings, (string) $row->key, $decoded);
        }

        return $settings;
    }

    /**
     * Сохранение настроек для scope.
     *
     * ОЖИДАНИЯ:
     *  - $settings — это ПЛОСКИЙ массив вида ['bestchange.api_key' => '...', 'language.sitename' => [...]];
     *  - обновляет ТОЛЬКО переданные ключи;
     *  - НЕ удаляет другие записи scope;
     *  - по умолчанию НЕ создаёт новые ключи (ожидается, что они заведены миграциями).
     *
     * Поведение для новых ключей управляется флагом config('dynamic_config.auto_create_keys'):
     *  - false (по умолчанию): логируем предупреждение и пропускаем;
     *  - true: создаём новую запись.
     *
     * @param Scope               $scope
     * @param array<string,mixed> $settings
     */
    public function save(Scope $scope, array $settings): void
    {
        if ($settings === []) {
            return;
        }

        $this->connection->transaction(function () use ($scope, $settings): void {
            $now = now();

            // 1. Получаем существующие key для этого scope
            $existingKeys = $this->connection
                ->table('dynamic_config_settings')
                ->where('scope_type', $scope->type)
                ->where('scope_id', $scope->id)
                ->pluck('key')
                ->all();

            $existingLookup = array_fill_keys(array_map('strval', $existingKeys), true);

            $autoCreate = (bool) config('dynamic_config.auto_create_keys', false);

            // 2. Обрабатываем каждый переданный ключ
            foreach ($settings as $key => $value) {
                $key = (string) $key;

                try {
                    $storedValue = $this->encodeValue($value);
                } catch (\Throwable $e) {
                    Log::error('DynamicConfig: ошибка кодирования значения перед сохранением', [
                        'scope_type' => $scope->type,
                        'scope_id'   => $scope->id,
                        'key'        => $key,
                        'error'      => $e->getMessage(),
                    ]);
                    continue;
                }

                // Нет записи с таким key в БД
                if (!isset($existingLookup[$key])) {
                    if ($autoCreate) {
                        // Создаём новый ключ (на dev/тесте)
                        $this->connection
                            ->table('dynamic_config_settings')
                            ->insert([
                                'scope_type' => $scope->type,
                                'scope_id'   => $scope->id,
                                'key'        => $key,
                                'value'      => $storedValue,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);

                        Log::info('DynamicConfig: создан новый ключ (auto_create_keys = true)', [
                            'scope_type' => $scope->type,
                            'scope_id'   => $scope->id,
                            'key'        => $key,
                        ]);
                    } else {
                        // На проде/боевом окружении только логируем, что ключ не существует
                        Log::warning('DynamicConfig: попытка обновить несуществующий ключ (нет записи в БД)', [
                            'scope_type' => $scope->type,
                            'scope_id'   => $scope->id,
                            'key'        => $key,
                        ]);
                    }

                    continue;
                }

                // Ключ существует — UPDATE value + updated_at (created_at не трогаем)
                $this->connection
                    ->table('dynamic_config_settings')
                    ->where('scope_type', $scope->type)
                    ->where('scope_id', $scope->id)
                    ->where('key', $key)
                    ->update([
                        'value'      => $storedValue,
                        'updated_at' => $now,
                    ]);
            }
        });
    }

    /**
     * Полное очищение scope (hard-delete) — удаляет все записи для scope.
     */
    public function clear(Scope $scope): void
    {
        $this->connection
            ->table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->delete();
    }

    // ---------------------------------------------------------------------
    // Encoder / Decoder
    // ---------------------------------------------------------------------

    /**
     * Декодирование значения из поля "value".
     *
     * encoder/decoder задаются в config('dynamic_config.encoder/decoder').
     * Если decoder не определён, используется json_decode() с JSON_THROW_ON_ERROR.
     *
     * @throws \JsonException
     */
    private function decodeValue(string $raw): mixed
    {
        $decoder = config('dynamic_config.decoder');

        if ($decoder && is_callable($decoder)) {
            return $decoder($raw);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Кодирование значения для записи в поле "value".
     *
     * encoder/decoder задаются в config('dynamic_config.encoder/decoder').
     * Если encoder не определён, используется json_encode() с JSON_THROW_ON_ERROR.
     *
     * @throws \JsonException
     */
    private function encodeValue(mixed $value): string
    {
        $encoder = config('dynamic_config.encoder');

        if ($encoder && is_callable($encoder)) {
            $encoded = $encoder($value);

            return is_string($encoded)
                ? $encoded
                : json_encode($encoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
