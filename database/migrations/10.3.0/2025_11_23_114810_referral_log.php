<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Применение изменений.
     *
     * ВАЖНО: для метода change() нужен doctrine/dbal.
     */
    public function up(): void
    {
        Schema::table('referral_log', function (Blueprint $table) {
            // Переводим деньги и проценты в DECIMAL
            $table->decimal('bonus_number', 24, 8)->default(0)->change();
            $table->decimal('fixed_bonus', 24, 8)->default(0)->change();
            $table->decimal('current_percent', 10, 4)->default(0)->change();

            // Индексы под аналитику
            $table->index('id_user', 'referral_log_id_user_index');
            $table->index('id_referral', 'referral_log_id_referral_index');
            $table->index('id_referral_link', 'referral_log_id_referral_link_index');
            $table->index('created_at', 'referral_log_created_at_index');
        });
    }

    /**
     * Откат изменений.
     */
    public function down(): void
    {
        Schema::table('referral_log', function (Blueprint $table) {
            // Возвращаем обратно в FLOAT (если нужно откатить)
            $table->float('bonus_number')->default(0)->change();
            $table->float('fixed_bonus')->default(0)->change();
            $table->float('current_percent')->default(0)->change();

            // Убираем индексы
            $table->dropIndex('referral_log_id_user_index');
            $table->dropIndex('referral_log_id_referral_index');
            $table->dropIndex('referral_log_id_referral_link_index');
            $table->dropIndex('referral_log_created_at_index');
        });
    }
};
