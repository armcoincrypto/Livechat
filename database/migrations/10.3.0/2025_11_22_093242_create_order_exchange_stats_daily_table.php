<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Таблица дневной статистики заявок обмена.
     */
    public function up(): void
    {
        Schema::create('order_exchange_stats_daily', function (Blueprint $table) {
            $table->id();

            // Бизнес-дата, за которую посчитана статистика
            $table->date('date')->unique();

            // Всего заявок
            $table->unsignedBigInteger('total_count')->default(0);

            // Выполненные (исполненные)
            $table->unsignedBigInteger('completed_count')->default(0);

            // Отклонённые / отменённые / неуспешные
            $table->unsignedBigInteger('rejected_count')->default(0);

            // В обработке (все живые статусы)
            $table->unsignedBigInteger('processing_count')->default(0);

            $table->timestamps();

            $table->index('date', 'order_exchange_stats_daily_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_exchange_stats_daily');
    }
};
