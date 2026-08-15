<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет индексы для ускорения выборок из logs-таблицы rates_history_logs.
 *
 * Что ускоряет:
 * - Фильтрацию по источнику + период (source, created_at)
 * - Фильтрацию по id источника + период (id_source, created_at)
 * - Быстрые выборки "последние записи" по времени (created_at)
 *
 * Важно:
 * - Индексы делаем отдельной миграцией, чтобы не трогать структуру таблицы.
 * - В down() индексы удаляются.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::table('rates_history_logs', function (Blueprint $table): void {
            // MySQL сам создаст BTREE, достаточно обычного index()

            if (! $this->hasIndex('rates_history_logs', 'rates_history_logs_source_created_at_idx')) {
                $table->index(['source', 'created_at'], 'rates_history_logs_source_created_at_idx');
            }

            if (! $this->hasIndex('rates_history_logs', 'rates_history_logs_id_source_created_at_idx')) {
                $table->index(['id_source', 'created_at'], 'rates_history_logs_id_source_created_at_idx');
            }

            if (! $this->hasIndex('rates_history_logs', 'rates_history_logs_created_at_idx')) {
                $table->index(['created_at'], 'rates_history_logs_created_at_idx');
            }
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('rates_history_logs', function (Blueprint $table): void {
            if ($this->hasIndex('rates_history_logs', 'rates_history_logs_source_created_at_idx')) {
                $table->dropIndex('rates_history_logs_source_created_at_idx');
            }

            if ($this->hasIndex('rates_history_logs', 'rates_history_logs_id_source_created_at_idx')) {
                $table->dropIndex('rates_history_logs_id_source_created_at_idx');
            }

            if ($this->hasIndex('rates_history_logs', 'rates_history_logs_created_at_idx')) {
                $table->dropIndex('rates_history_logs_created_at_idx');
            }
        });
    }

    /**
     * Проверяет наличие индекса в таблице (по имени).
     *
     * @param non-empty-string $table
     * @param non-empty-string $indexName
     *
     * @return bool
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        // В Schema нет нативной проверки индексов по имени, поэтому используем show index.
        // Это безопасно и работает быстро.
        $connection = Schema::getConnection();
        $database = (string) $connection->getDatabaseName();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $connection->select(
            'SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return !empty($rows);
    }
};
