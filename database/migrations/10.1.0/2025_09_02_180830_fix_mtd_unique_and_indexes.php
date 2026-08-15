<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Схлопываем дубликаты по id_task (оставляем запись с максимальным id)
        //   — без сырых SQL и без Doctrine, только Query Builder.
        $dupes = DB::table('merchants_transaction_data')
            ->select('id_task', DB::raw('COUNT(*) as c'), DB::raw('MAX(id) as max_id'))
            ->groupBy('id_task')
            ->having('c', '>', 1)
            ->get();

        foreach ($dupes as $d) {
            DB::table('merchants_transaction_data')
                ->where('id_task', $d->id_task)
                ->where('id', '<', $d->max_id)   // оставляем самую «свежую»
                ->delete();
        }

        // 2) Индексы (без Doctrine)
        Schema::table('merchants_transaction_data', function (Blueprint $table) {
            // одна заявка → одна запись
            if (! $this->indexExists('merchants_transaction_data', 'uniq_mtd_task')) {
                $table->unique('id_task', 'uniq_mtd_task');
            }

            // быстрые выборки по мерчанту за время
            if (! $this->indexExists('merchants_transaction_data', 'idx_mtd_merchant_time')) {
                $table->index(['id_merchant', 'created_at'], 'idx_mtd_merchant_time');
            }

            // отчёты по направлениям (если используются)
            if (! $this->indexExists('merchants_transaction_data', 'idx_mtd_direction')) {
                $table->index('id_direction_exchange', 'idx_mtd_direction');
            }

            // выборки по названию провайдера
            if (! $this->indexExists('merchants_transaction_data', 'idx_mtd_service')) {
                $table->index('service_name', 'idx_mtd_service');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants_transaction_data', function (Blueprint $table) {
            // Снимаем индексы (дубликаты назад не «восстанавливаем» — это и не нужно).
            if ($this->indexExists('merchants_transaction_data', 'uniq_mtd_task')) {
                $table->dropUnique('uniq_mtd_task');
            }
            if ($this->indexExists('merchants_transaction_data', 'idx_mtd_merchant_time')) {
                $table->dropIndex('idx_mtd_merchant_time');
            }
            if ($this->indexExists('merchants_transaction_data', 'idx_mtd_direction')) {
                $table->dropIndex('idx_mtd_direction');
            }
            if ($this->indexExists('merchants_transaction_data', 'idx_mtd_service')) {
                $table->dropIndex('idx_mtd_service');
            }
        });
    }

    /**
     * Проверка существования индекса без Doctrine — через information_schema.
     */
    private function indexExists(string $table, string $index): bool
    {
        $schema = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
