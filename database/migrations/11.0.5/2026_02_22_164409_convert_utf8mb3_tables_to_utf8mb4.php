<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Конвертирует таблицы с устаревшей кодировкой utf8mb3 в utf8mb4.
 *
 * Почему это нужно:
 * - utf8mb3 не поддерживает 4-байтовые символы (эмодзи и часть Unicode).
 * - Разнобой кодировок/колляций в одной БД может вызывать ошибки сравнения (Illegal mix of collations).
 * - utf8mb4 — современный стандарт для текстовых данных.
 *
 * Что делает:
 * - Проверяет существование таблицы.
 * - Проверяет текущую кодировку таблицы (только если она utf8mb3/utf8 — конвертирует).
 * - Выполняет ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci.
 *
 * Важно:
 * - down() не реализован намеренно: откат обратно на utf8mb3 — плохая практика и может привести к потере данных.
 */
return new class extends Migration
{
    /**
     * Таблицы из дампа, которые были в utf8mb3.
     *
     * @var list<string>
     */
    private array $tables = [
        'currencies',
        'direction_exchange',
        'group_parser_exchange',
        'news',
        'pages_static',
        'payments',
        'settings_referrals',
    ];

    /**
     * @var non-empty-string
     */
    private string $targetCharset = 'utf8mb4';

    /**
     * Максимально совместимая колляция (подходит и MySQL, и MariaDB).
     *
     * @var non-empty-string
     */
    private string $targetCollation = 'utf8mb4_unicode_ci';

    /**
     * @return void
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $current = $this->getTableCollation($table);
            if ($current === null) {
                // Если не удалось определить — лучше не рисковать автоматическим ALTER.
                continue;
            }

            // Collation выглядит как "utf8mb3_unicode_ci" или "utf8_general_ci" и т.п.
            // Нас интересует именно charset: utf8mb3 / utf8 (устаревшее имя) -> переводим на utf8mb4.
            $currentCharset = $this->extractCharsetFromCollation($current);

            if ($currentCharset === 'utf8mb4') {
                continue; // уже ок
            }

            if ($currentCharset !== 'utf8mb3' && $currentCharset !== 'utf8') {
                // Не трогаем экзотику (latin1 и т.п.) без явного решения.
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET %s COLLATE %s',
                str_replace('`', '``', $table),
                $this->targetCharset,
                $this->targetCollation
            ));
        }
    }

    /**
     * Откат не делаем намеренно.
     *
     * @return void
     */
    public function down(): void
    {
        // intentionally left blank
    }

    /**
     * Получить текущую collation таблицы из INFORMATION_SCHEMA.
     *
     * @param non-empty-string $table
     * @return string|null
     */
    private function getTableCollation(string $table): ?string
    {
        $connection = Schema::getConnection();
        $dbName = (string) $connection->getDatabaseName();

        /** @var array<int, object> $rows */
        $rows = $connection->select(
            'SELECT TABLE_COLLATION AS collation
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
             LIMIT 1',
            [$dbName, $table]
        );

        if (empty($rows)) {
            return null;
        }

        $collation = (string) ($rows[0]->collation ?? '');
        return $collation !== '' ? $collation : null;
    }

    /**
     * Преобразует TABLE_COLLATION -> charset (простое извлечение до первого "_").
     *
     * Пример:
     * - utf8mb3_unicode_ci -> utf8mb3
     * - utf8_general_ci    -> utf8
     * - utf8mb4_0900_ai_ci -> utf8mb4
     *
     * @param non-empty-string $collation
     * @return string
     */
    private function extractCharsetFromCollation(string $collation): string
    {
        $pos = strpos($collation, '_');
        if ($pos === false) {
            return $collation;
        }

        return substr($collation, 0, $pos);
    }
};
