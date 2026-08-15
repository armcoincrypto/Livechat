<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // 1) состояние записи: active|resolved (по умолчанию active для startProblem)
            $table->enum('state', ['active', 'resolved'])->default('active')->after('level')->index();

            // 2) покрывающий составной индекс под быстрый поиск последней записи
            // NB: MySQL не поддерживает desc в индексе < 8.0.13 — оставим обычный составной
            $table->index(['module','name','code','entity_type','entity_id','occurred_at','id'], 'syslogs_key_lookup');

            // 3) при желании — вычисляемый ключ (если БД позволяет) для ускорения (опционально)
            // $table->string('problem_key', 512)->virtualAs("concat_ws('#', module, coalesce(name,''), code, coalesce(entity_type,''), coalesce(entity_id,''))")->index('syslogs_problem_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex('syslogs_key_lookup');
            // $table->dropIndex('syslogs_problem_key_idx');
            $table->dropColumn('state');
        });
    }
};
