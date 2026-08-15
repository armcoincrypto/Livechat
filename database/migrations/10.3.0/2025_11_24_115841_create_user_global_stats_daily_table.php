<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_global_stats_daily', function (Blueprint $table) {
            $table->id();

            $table->date('date')->unique();

            // Пользователи
            $table->unsignedBigInteger('total_users')->default(0);       // всего пользователей на конец дня
            $table->unsignedBigInteger('new_users')->default(0);         // новые за день
            $table->unsignedBigInteger('active_users')->default(0);      // логинились или делали заявки
            $table->unsignedBigInteger('users_with_orders')->default(0); // сделали хотя бы 1 заявку

            // Заявки
            $table->unsignedBigInteger('total_orders')->default(0);
            $table->unsignedBigInteger('successful_orders')->default(0);
            $table->unsignedBigInteger('failed_orders')->default(0);
            $table->unsignedBigInteger('canceled_orders')->default(0);

            // Деньги (USD)
            $table->decimal('total_volume_usd', 24, 8)->default(0);
            $table->decimal('total_profit_usd', 24, 8)->default(0);

            // Средние показатели
            $table->decimal('avg_orders_per_active_user', 16, 4)->default(0);
            $table->decimal('avg_volume_per_active_user', 24, 8)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_global_stats_daily');
    }
};
