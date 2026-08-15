<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('settings_limit_profiles', function (Blueprint $table) {
            // Минимальный интервал между заявками в секундах (0 = отключено)
            $table->unsignedInteger('min_interval_between_orders_seconds')
                ->default(0)
                ->after('order_limit_minutes');

            // Сколько первых заявок попадает под особое ограничение (0 = нет спец.лимита)
            $table->unsignedInteger('first_orders_window_count')
                ->default(0)
                ->after('min_interval_between_orders_seconds');

            // Макс. сумма "Отдаю" для этих первых заявок (nullable = нет лимита по сумме)
            $table->decimal('first_orders_max_amount', 24, 8)
                ->nullable()
                ->after('first_orders_window_count');
        });
    }

    public function down(): void
    {
        Schema::table('settings_limit_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'min_interval_between_orders_seconds',
                'first_orders_window_count',
                'first_orders_max_amount',
            ]);
        });
    }
};
