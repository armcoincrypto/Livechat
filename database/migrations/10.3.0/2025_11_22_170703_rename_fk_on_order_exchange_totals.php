<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Потом можем повесить нормальный FK / индекс
        Schema::table('order_exchange_totals', function (Blueprint $table) {

            // 1. Найдём реальные foreign keys
            $fks = DB::select("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'order_exchange_totals'
          AND COLUMN_NAME = 'id_task'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

            // 2. Удаляем все найденные foreign keys
            foreach ($fks as $fk) {
                $name = $fk->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE `order_exchange_totals` DROP FOREIGN KEY `$name`");
            }

            // 3. Теперь можно удалить индекс со старым именем (если он существует)
            DB::statement("
        ALTER TABLE `order_exchange_totals`
        DROP INDEX `tasks_convert_log_id_task_foreign`
    ");

            // 4. Создаём новый внешний ключ с правильным именем
            $table->foreign('id_task', 'order_exchange_totals_id_task_foreign')
                ->references('id')
                ->on('tasks')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('order_exchange_totals', function (Blueprint $table) {
            // Откат: удаляем новый FK
            $table->dropForeign('order_exchange_totals_id_task_foreign');

            // При желании можем вернуть старый индекс, если нужен:
            // DB::statement('ALTER TABLE `order_exchange_totals` ADD INDEX `tasks_convert_log_id_task_foreign` (`id_task`)');
        });
    }
};
