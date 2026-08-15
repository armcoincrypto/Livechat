<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Удаляем устаревшую таблицу логов резервов.
     *
     * Таблица `reserves_logs` больше не используется:
     * - заменена на reserve_ledgers (аудит)
     * - агрегаты строятся в reserve_ledger_daily
     */
    public function up(): void
    {
        if (Schema::hasTable('reserves_logs')) {
            Schema::drop('reserves_logs');
        }
    }

    /**
     * Восстановление таблицы (rollback).
     *
     * ВАЖНО:
     * - используется ТОЛЬКО для отката миграций
     * - бизнес-логика больше не опирается на эту таблицу
     */
    public function down(): void
    {
        Schema::create('reserves_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('id_reserve')->default(0)->index();
            $table->unsignedInteger('id_currency')->default(0)->index();

            // Денежные значения (как было ранее)
            $table->string('amount', 191)->default('0');
            $table->string('amount_from', 191)->default('0');
            $table->string('amount_to', 191)->default('0');

            // Тип операции (устаревший)
            $table->integer('type_reserve')->default(0)->index();

            // Связи
            $table->unsignedBigInteger('id_task')->default(0)->index();
            $table->unsignedInteger('id_direction_exchange')->default(0)->index();

            $table->timestamps();
        });
    }
};
