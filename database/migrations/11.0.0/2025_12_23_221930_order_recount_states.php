<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_recount_states', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->primary();

            // общая дата последнего пересчёта (используется и для currency/global, и для floating)
            $table->timestamp('last_recalculated_at')->nullable();

            // last rate string
            $table->string('last_rate_value', 64)->nullable();

            // статус-сессия (для “пересчёта внутри статуса”)
            $table->unsignedInteger('status_last')->nullable();
            $table->timestamp('status_entered_at')->nullable();
            $table->unsignedInteger('recount_in_status_count')->default(0);

            // тех. поля
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('fail_streak')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            $table->index(['status_last', 'status_entered_at'], 'ors_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_recount_states');
    }
};
