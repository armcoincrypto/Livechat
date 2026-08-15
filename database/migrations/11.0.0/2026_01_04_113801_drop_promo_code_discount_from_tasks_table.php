<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Удаляем legacy-поле процента промокода
            if (Schema::hasColumn('tasks', 'promo_code_discount')) {
                $table->dropColumn('promo_code_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Восстанавливаем колонку при rollback
            if (!Schema::hasColumn('tasks', 'promo_code_discount')) {
                $table->string('promo_code_discount', 191)
                    ->nullable()
                    ->after('promo_code_value');
            }
        });
    }
};
