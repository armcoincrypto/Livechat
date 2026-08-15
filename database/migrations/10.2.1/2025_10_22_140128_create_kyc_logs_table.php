<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Таблица логов KYC.
     *
     * Содержит:
     * - provider               — провайдер проверки (например: sumsub, manual…)
     * - user_id                — ID пользователя (FK)
     * - status                 — произвольный статус события/шага
     * - external_user_id       — внешний идентификатор (u-1 / iex-client-1 и т.п.)
     * - applicant_id           — ID аппликанта в провайдере (если есть)
     * - correlation_id         — корреляция из API провайдера
     * - review_status          — статус ревью у провайдера (init, pending, completed…)
     * - review_answer          — ответ по ревью (GREEN/RED/…)
     * - reject_labels          — массив ярлыков причин отказа (JSON)
     * - payload                — сырой полезный груз запроса/ответа (JSON)
     * - meta                   — любое дополнительное приложение данных (JSON)
     * - occurred_at            — метка времени события (для хронологии)
     */
    public function up(): void
    {
        Schema::create('kyc_logs', function (Blueprint $table) {
            $table->id();

            // Ключи маршрутизации
            $table->string('provider', 64)->index();
            $table->string('event', 64)->index();
            $table->string('status', 32)->nullable()->index();

            // Идентификаторы
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->foreign('user_id', 'kyc_logs_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->string('subject_external_id', 191)->nullable()->index();

            // Бизнес-этап и результат
            $table->string('stage', 32)->nullable()->index();
            $table->string('outcome', 32)->nullable()->index();
            $table->text('outcome_reason')->nullable();

            // Универсальные JSON-поля
            $table->json('response_data')->nullable();  // объединённые данные запроса/ответа/контекста
            $table->json('meta')->nullable();           // техническая мета-информация приложения

            // Временные метки
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_logs');
    }
};
