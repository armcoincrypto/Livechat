<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_profit_stats', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Дата статистики
            $table->date('stat_date')->index();

            // Идентификатор направления
            $table->unsignedBigInteger('direction_id')->index();

            $table->string('direction_name')->nullable();
            $table->string('currency_from')->nullable();
            $table->string('currency_to')->nullable();

            // Кол-во заявок
            $table->unsignedBigInteger('total_orders')->default(0);

            // Суммарная прибыль в USD
            $table->decimal('total_profit_usd', 30, 18)->default(0);

            // Средняя прибыль
            $table->decimal('avg_profit_usd', 30, 18)->default(0);

            // Мин/макс прибыль
            $table->decimal('min_profit_usd', 30, 18)->default(0);
            $table->decimal('max_profit_usd', 30, 18)->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'direction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_profit_stats');
    }
};
