<?php

// database/migrations/2026_01_04_200600_upgrade_tasks_requisites_attached.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_requisites_attached', function (Blueprint $table) {
            // user_agent: 191 часто мало
            $table->text('user_agent')->nullable()->change();

            // ip: IPv6 максимум 45
            $table->string('ip_address', 45)->nullable()->change();

            // индексы
            $table->index(['id_task', 'id'], 'idx_task_requisites_attached_task');
            $table->index(['id_manager', 'id'], 'idx_task_requisites_attached_manager');
            $table->index(['created_at'], 'idx_task_requisites_attached_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks_requisites_attached', function (Blueprint $table) {
            $table->dropIndex('idx_task_requisites_attached_task');
            $table->dropIndex('idx_task_requisites_attached_manager');
            $table->dropIndex('idx_task_requisites_attached_created_at');

            // откат типов делать не рекомендую (можно потерять данные), поэтому не меняю назад.
        });
    }
};
