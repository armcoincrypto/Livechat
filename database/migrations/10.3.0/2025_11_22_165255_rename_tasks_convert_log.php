<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Переименование таблицы
        if (Schema::hasTable('tasks_convert_log') && ! Schema::hasTable('order_exchange_totals')) {
            Schema::rename('tasks_convert_log', 'order_exchange_totals');
        }

        // 2. Обновление структуры
        Schema::table('order_exchange_totals', function (Blueprint $table) {
            // Переименуем to_usd → exchange_usd
            if (Schema::hasColumn('order_exchange_totals', 'to_usd')) {
                $table->renameColumn('to_usd', 'exchange_usd');
            }

            // Удаление to_rub
            if (Schema::hasColumn('order_exchange_totals', 'to_rub')) {
                $table->dropColumn('to_rub');
            }

            // Индекс для аналитики (по дате) — добавляем только если его нет
            if (! DB::getSchemaBuilder()->hasIndex('order_exchange_totals', 'order_exchange_totals_created_at_index')) {
                $table->index('created_at', 'order_exchange_totals_created_at_index');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_exchange_totals')) {
            Schema::table('order_exchange_totals', function (Blueprint $table) {
                // Удаляем индекс по дате, если он есть
                if (DB::getSchemaBuilder()->hasIndex('order_exchange_totals', 'order_exchange_totals_created_at_index')) {
                    $table->dropIndex('order_exchange_totals_created_at_index');
                }

                // Вернуть to_rub при откате
                if (! Schema::hasColumn('order_exchange_totals', 'to_rub')) {
                    $table->float('to_rub')->nullable();
                }

                // Переименуем exchange_usd обратно → to_usd
                if (Schema::hasColumn('order_exchange_totals', 'exchange_usd')) {
                    $table->renameColumn('exchange_usd', 'to_usd');
                }

                // Откат: удаляем новый FK, если он есть
                if (DB::getSchemaBuilder()->hasIndex('order_exchange_totals', 'order_exchange_totals_id_task_foreign')) {
                    $table->dropForeign('order_exchange_totals_id_task_foreign');
                }

// Возвращаем старый FK
                $table->foreign('id_task', 'tasks_convert_log_id_task_foreign')
                    ->references('id')
                    ->on('tasks')
                    ->onDelete('cascade');
            });

            // Переименовываем таблицу обратно
            if (! Schema::hasTable('tasks_convert_log')) {
                Schema::rename('order_exchange_totals', 'tasks_convert_log');
            }
        }
    }
};
