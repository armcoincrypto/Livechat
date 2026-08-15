<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('direction_exchange_stats_daily', function (Blueprint $table) {
            $table->id();

            // День статистики
            $table->date('stat_date')->index();

            // Направление (БЕЗ FK, чтобы не потерять историю при удалении направления)
            $table->unsignedBigInteger('direction_exchange_id')->index();

            // Статусы
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedInteger('rejected_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('processing_orders')->default(0);

            // Объёмы в USD (пока заполняем нулями, потом легко добавишь SUM(...) из tasks)
            $table->decimal('total_amount_from_usd', 30, 8)->default(0);
            $table->decimal('total_amount_to_usd', 30, 8)->default(0);

            // Прибыль по направлению (из твоей системы прибыли, пока 0)
            $table->decimal('total_profit_usd', 30, 8)->default(0);

            // Клиенты
            $table->unsignedInteger('unique_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);

            $table->timestamps();

            $table->unique(
                ['stat_date', 'direction_exchange_id'],
                'direction_exchange_stats_daily_date_direction_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direction_exchange_stats_daily');
    }
};
