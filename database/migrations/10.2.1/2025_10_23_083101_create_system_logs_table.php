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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();

            // Где возникло
            $table->string('module', 64)->index();         // 'calculator', 'kyc', 'payments', 'webhook', ...
            $table->string('level', 20)->index();          // info|warning|error|critical

            // Привязка к сущности (универсальная полиморфная ссылка)
            $table->string('entity_type', 120)->nullable()->index(); // App\Models\DirectionExchange
            $table->string('entity_id', 64)->nullable()->index();    // 42 / ULID / UUID

            // Машинный код + человекочитаемое сообщение
            $table->string('code', 128)->nullable()->index();        // course_source_not_found
            $table->string('message');                                // "Источник курса не найден"

            // Детали
            $table->json('context')->nullable();                      // {directionExchange_id, tech_name, ...}
            $table->timestamp('occurred_at')->nullable()->index();

            // Для дедупликации (по желанию)
            $table->string('dedup_hash', 64)->nullable()->index();

            $table->timestamps();

            // Композитные индексы для быстрых выборок
            $table->index(['entity_type', 'entity_id', 'occurred_at']);
            $table->index(['module', 'code', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
