<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserve_ledger_daily', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // День (локально для отчётов)
            $table->date('day')->index();

            // IDs под твою БД
            $table->integer('reserve_id')->index();                 // reserves.id (int signed)
            $table->integer('direction_exchange_id')->nullable()->index(); // direction_exchange.id (int signed)
            $table->integer('currency_id')->nullable()->index();

            // Срез по типу события
            $table->string('action', 40)->index(); // hold_out, commit_in, release_out, manual_adjust...

            // Счётчики
            $table->unsignedBigInteger('count_total')->default(0);
            $table->unsignedBigInteger('count_in')->default(0);
            $table->unsignedBigInteger('count_out')->default(0);

            // Суммы (точно)
            $table->decimal('sum_delta', 65, 18)->default(0);
            $table->decimal('sum_in', 65, 18)->default(0);
            $table->decimal('sum_out', 65, 18)->default(0);

            // Для удобства “последний баланс дня” (опционально, но очень полезно)
            $table->decimal('closing_balance', 65, 18)->nullable();

            $table->timestamps();

            // Один ряд на (day + reserve + action + direction_exchange) — максимально аналитично.
            // Если direction_exchange_id NULL — это системные/ручные операции без направления.
            $table->unique(
                ['day', 'reserve_id', 'action', 'direction_exchange_id'],
                'uq_rld_day_reserve_action_direction'
            );

            // Частые запросы: по резерву за период
            $table->index(['reserve_id', 'day'], 'idx_rld_reserve_day');

            // Частые запросы: по направлению за период
            $table->index(['direction_exchange_id', 'day'], 'idx_rld_direction_day');

            // Частые запросы: по action за период
            $table->index(['action', 'day'], 'idx_rld_action_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserve_ledger_daily');
    }
};
