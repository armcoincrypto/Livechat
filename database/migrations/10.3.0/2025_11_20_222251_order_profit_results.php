<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_profit_results', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Заявка
            $table->unsignedBigInteger('task_id')->index();

            // Итоговая прибыль в исходной валюте (валюта currency1 направления)
            $table->decimal('profit_amount', 30, 18)->default(0);
            $table->string('profit_currency_code', 16);

            // Прибыль в базовой валюте (обычно USD)
            $table->decimal('profit_amount_usd', 30, 18)->default(0);
            $table->string('base_currency_code', 16)->default('USD');

            // Эффективный процент прибыли от суммы «отдаю»
            $table->decimal('profit_percent_effective', 20, 8)->default(0);

            // Снимок курсов и детализация (компоненты)
            $table->json('rates_snapshot')->nullable();
            $table->json('breakdown_json')->nullable();

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique('task_id');

            // По желанию:
            // $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_profit_results');
    }
};
