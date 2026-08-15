<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Убирает zero-date '0000-00-00 00:00:00' в updated_at
 * и переводит колонку в безопасный формат:
 *
 * timestamp NULL DEFAULT NULL
 *
 * Причина:
 * - strict mode в MySQL 8+
 * - ошибки при сохранении через Eloquent
 * - проблемы при будущих миграциях
 */
return new class extends Migration
{
    /**
     * Таблицы, в которых исправляем updated_at.
     *
     * @var list<string>
     */
    private array $tables = [
        'code_currency',
        'group_parser_exchange',
        'pages_static',
        'parser_exchange',
        'payments',
        'settings_referrals',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {

            if (!Schema::hasTable($table)) {
                continue;
            }

            if (!Schema::hasColumn($table, 'updated_at')) {
                continue;
            }

            // 1. Заменяем zero-date на NULL
            DB::statement("
                UPDATE `{$table}`
                SET `updated_at` = NULL
                WHERE `updated_at` = '0000-00-00 00:00:00'
            ");

            // 2. Меняем структуру колонки
            DB::statement("
                ALTER TABLE `{$table}`
                MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL
            ");
        }
    }

    /**
     * Откат intentionally не возвращает zero-date,
     * потому что это антипаттерн.
     *
     * @return void
     */
    public function down(): void
    {
        // Ничего не делаем.
    }
};
