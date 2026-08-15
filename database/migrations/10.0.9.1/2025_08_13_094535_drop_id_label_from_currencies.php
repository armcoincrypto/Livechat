<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Удаляем колонку id_label из currencies (сначала FK/индекс, если они есть)
        if (Schema::hasColumn('currencies', 'id_label')) {
            Schema::table('currencies', function (Blueprint $table) {
                // Удаляем саму колонку
                $table->dropColumn(['id_label']);
            });
        }
    }

    public function down(): void
    {
        // Возвращаем колонку id_label (nullable) и вешаем FK обратно
        if (!Schema::hasColumn('currencies', 'id_label')) {
        }
    }
};
