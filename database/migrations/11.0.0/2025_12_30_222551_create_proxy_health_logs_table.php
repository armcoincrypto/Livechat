<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proxy_health_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Прокси
            $table->unsignedBigInteger('proxy_id');

            /**
             * Контекст использования прокси:
             * - bestchange
             * - parser
             * - gateway
             * - manual_test
             * - custom
             */
            $table->string('context', 50);

            // Результат запроса
            $table->boolean('success')->default(false);

            // HTTP статус (если был HTTP)
            $table->unsignedSmallInteger('http_status')->nullable();

            // Время ответа (мс)
            $table->unsignedInteger('latency_ms')->nullable();

            // Сообщение ошибки (timeout, connection refused, etc.)
            $table->string('error', 1024)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // --------------------
            // Индексы
            // --------------------
            $table->index(['proxy_id', 'created_at'], 'proxy_health_logs_proxy_time_idx');
            $table->index(['context', 'created_at'], 'proxy_health_logs_context_time_idx');
            $table->index(['success', 'created_at'], 'proxy_health_logs_success_time_idx');

            // FK (если таблица proxies существует)
            $table->foreign('proxy_id')
                ->references('id')
                ->on('proxies')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxy_health_logs');
    }
};
