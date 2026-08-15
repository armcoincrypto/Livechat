<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('direction_exchange_selector_fee', function (Blueprint $table) {
            // Часто ищем по направлению
            $table->index('id_direction_exchange', 'idx_desf_direction');

            // Если где-то листим записи и сортируем по id — поможет покрыть оба условия
            $table->index(['id_direction_exchange', 'id'], 'idx_desf_direction_id');
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange_selector_fee', function (Blueprint $table) {
            $table->dropIndex('idx_desf_direction');
            $table->dropIndex('idx_desf_direction_id');
        });
    }
};
