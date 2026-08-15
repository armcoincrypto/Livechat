<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Тип скидки промокода: percent|fixed
            if (!Schema::hasColumn('tasks', 'promo_code_discount_type')) {
                $table->string('promo_code_discount_type', 16)
                    ->nullable()
                    ->after('promo_code_discount');
            }

            // Снапшот кода промокода (на случай удаления/изменения promo_codes)
            if (!Schema::hasColumn('tasks', 'promo_code_code')) {
                $table->string('promo_code_code', 64)
                    ->nullable()
                    ->after('promo_code_discount_type');
            }

            // Индекс на id промокода (ускоряет выборки/аналитику)
            $table->index('id_promo_code', 'tasks_id_promo_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'promo_code_code')) {
                $table->dropColumn('promo_code_code');
            }

            if (Schema::hasColumn('tasks', 'promo_code_discount_type')) {
                $table->dropColumn('promo_code_discount_type');
            }

            // dropIndex требует имя
            $table->dropIndex('tasks_id_promo_code_idx');
        });
    }
};
