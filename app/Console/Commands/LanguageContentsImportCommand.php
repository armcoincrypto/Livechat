<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class LanguageContentsImportCommand extends Command
{
    protected $signature = 'language:import
        {--file= : JSON-файл экспорта (по умолчанию storage/app/exports/language_contents.json)}
        {--scope-id=1 : scope_id для language (обычно 1)}
        {--default-locale=ru : локаль, куда кладём текст если он не translation-массив}
        {--force : перезаписывать существующие ключи language.*}';

    protected $description = 'Импортировать language_contents в DynamicConfig (scope_type=language).';

    public function handle(): int
    {
        $file = (string) ($this->option('file') ?: storage_path('app/exports/language_contents.json'));

        if (!File::exists($file)) {
            $this->error("Файл не найден: {$file}");
            return self::FAILURE;
        }

        $raw = File::get($file);

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->error('Невозможно прочитать JSON: ' . $e->getMessage());
            return self::FAILURE;
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            $this->error('В JSON нет секции data.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('dynamic_config_settings')) {
            $this->error('Таблица dynamic_config_settings не найдена.');
            return self::FAILURE;
        }

        $scopeId       = (int) $this->option('scope-id');
        $defaultLocale = (string) $this->option('default-locale');
        $force         = (bool) $this->option('force');

        // Эти поля не переносим в language.*
        $skipColumns = [
            'id', 'created_at', 'updated_at',
        ];

        $countTotal = 0;
        $countSaved = 0;
        $countSkip  = 0;

        foreach ($data as $column => $value) {
            $column = (string) $column;

            if (in_array($column, $skipColumns, true)) {
                continue;
            }

            $countTotal++;

            $dynKey = 'language.' . $column;

            $normalized = $this->normalizeLanguageValue($value, $defaultLocale);

            // Если нормализованное значение пустое — можно пропустить
            if ($normalized === null) {
                $countSkip++;
                continue;
            }

            // Если не force — не перезаписываем существующие ключи
            if (!$force) {
                $exists = DB::table('dynamic_config_settings')
                    ->where('scope_type', 'language')
                    ->where('scope_id', $scopeId)
                    ->where('key', $dynKey)
                    ->exists();

                if ($exists) {
                    $countSkip++;
                    continue;
                }
            }

            DB::table('dynamic_config_settings')->updateOrInsert(
                [
                    'scope_type' => 'language',
                    'scope_id'   => $scopeId,
                    'key'        => $dynKey,
                ],
                [
                    // value — JSON
                    'value'      => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    // created_at заполняется только если вставка, MySQL сам подставит при updateOrInsert не всегда —
                    // поэтому добавляем и его (не страшно).
                    'created_at' => now(),
                ]
            );

            $countSaved++;
        }

        $this->info("✅ Импорт завершён.");
        $this->line("Всего полей: {$countTotal}");
        $this->line("Записано: {$countSaved}");
        $this->line("Пропущено: {$countSkip}");

        return self::SUCCESS;
    }

    /**
     * Нормализует значение из language_contents к формату translation:
     *  - если строка содержит JSON {"ru":"...","en":"..."} → вернёт массив локалей;
     *  - если строка содержит JSON с числовыми ключами {"0":"<","1":"p"...} → склеит в строку и положит в defaultLocale;
     *  - если просто строка → положит в defaultLocale;
     *  - если [] или пусто → null.
     */
    private function normalizeLanguageValue(mixed $value, string $defaultLocale): ?array
    {
        if ($value === null) {
            return null;
        }

        // В дампе часто лежит JSON-строка в колонке (например {"ru":"...","en":"..."})
        if (is_string($value)) {
            $trim = trim($value);

            if ($trim === '' || $trim === '[]') {
                return null;
            }

            // Пытаемся распарсить JSON
            if (($trim[0] ?? '') === '{' || ($trim[0] ?? '') === '[') {
                try {
                    $decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);

                    // Если декодировался массив — анализируем структуру
                    if (is_array($decoded)) {
                        return $this->normalizeDecodedArray($decoded, $defaultLocale);
                    }
                } catch (\Throwable) {
                    // не JSON — просто строка
                }
            }

            // Обычная строка → default locale
            return [
                $defaultLocale => $trim,
            ];
        }

        // Если уже массив (редко, но возможно)
        if (is_array($value)) {
            return $this->normalizeDecodedArray($value, $defaultLocale);
        }

        // Скаляр → строка
        if (is_scalar($value)) {
            return [
                $defaultLocale => (string) $value,
            ];
        }

        return null;
    }

    /**
     * Нормализация массива после json_decode:
     * - translation-массив: ['ru'=>'..','en'=>'..'] → вернуть как есть (убрав null)
     * - “массив символов”: ['0'=>'<','1'=>'p',...] → склеить и положить в defaultLocale
     */
    private function normalizeDecodedArray(array $decoded, string $defaultLocale): ?array
    {
        if ($decoded === []) {
            return null;
        }

        $keys = array_keys($decoded);

        // 1) Translation-массив (есть ru/en/...)
        $hasLocaleKeys = false;
        foreach ($keys as $k) {
            if (is_string($k) && preg_match('/^[a-z]{2,5}$/', $k)) {
                $hasLocaleKeys = true;
                break;
            }
        }

        if ($hasLocaleKeys) {
            // чистим null значения
            $out = [];
            foreach ($decoded as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                if ($v === null) {
                    continue;
                }
                $out[$k] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $out === [] ? null : $out;
        }

        // 2) “Массив символов” (ключи 0..N)
        $allNumericKeys = true;
        foreach ($keys as $k) {
            if (!is_int($k) && !(is_string($k) && ctype_digit($k))) {
                $allNumericKeys = false;
                break;
            }
        }

        if ($allNumericKeys) {
            // сортируем по числовому порядку ключей
            uksort($decoded, static function ($a, $b) {
                return (int) $a <=> (int) $b;
            });

            $text = implode('', array_map(static fn($v) => (string) $v, $decoded));

            $text = trim($text);
            if ($text === '') {
                return null;
            }

            return [
                $defaultLocale => $text,
            ];
        }

        // 3) Любой другой массив → сохраняем как JSON в defaultLocale (чтобы не потерять данные)
        return [
            $defaultLocale => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];
    }
}
