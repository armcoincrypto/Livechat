<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Добавляем колонку active_flag, если её нет
            if (!Schema::hasColumn('system_logs', 'active_flag')) {
                $table->tinyInteger('active_flag')
                    ->default(0)               // 0 — resolved, 1 — active
                    ->index()
                    ->after('state')
                    ->comment('1=active, 0=resolved (для уникального индекса и быстрого поиска)');
            }

            // Создаём уникальный индекс (исключает >1 активной строки на ключ)
            $indexName = 'syslogs_unique_active_key';
            $hasIndex = collect(Schema::getConnection()
                ->select("SHOW INDEX FROM system_logs WHERE Key_name = ?", [$indexName]))
                ->isNotEmpty();

            if (! $hasIndex) {
                $table->unique(
                    ['module', 'name', 'code', 'entity_type', 'entity_id', 'active_flag'],
                    $indexName
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Удаляем индекс и колонку при откате
            if (Schema::hasColumn('system_logs', 'active_flag')) {
                $table->dropUnique('syslogs_unique_active_key');
                $table->dropColumn('active_flag');
            }
        });
    }
};
