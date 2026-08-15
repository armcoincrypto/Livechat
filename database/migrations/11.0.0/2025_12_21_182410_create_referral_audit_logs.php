<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referral_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Тип события (строго фиксируем)
            $table->string('event', 64)->index();
            // Уровень (info|warning|error)
            $table->string('level', 16)->default('info')->index();

            // Контекст
            $table->unsignedBigInteger('partner_user_id')->nullable()->index();
            $table->unsignedBigInteger('client_user_id')->nullable()->index();
            $table->unsignedBigInteger('referral_link_id')->nullable()->index();
            $table->unsignedBigInteger('referral_program_id')->nullable()->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();

            // Основное сообщение
            $table->string('message', 500);

            // Любые данные (json)
            $table->json('meta')->nullable();

            // Идентификатор запроса (полезно для дебага цепочек)
            $table->string('trace_id', 64)->nullable()->index();

            $table->timestamps();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_audit_logs');
    }
};
