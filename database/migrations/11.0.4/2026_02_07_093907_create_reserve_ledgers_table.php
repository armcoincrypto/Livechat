<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserve_ledgers', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->integer('reserve_id')->index();

            $table->integer('direction_exchange_id')->nullable()->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->integer('currency_id')->nullable()->index();

            $table->string('action', 40)->index();       // hold_out, commit_in, release_out, manual_adjust, ...
            $table->string('source_type', 20)->index();  // task, admin, cron, system
            $table->unsignedBigInteger('source_id')->default(0)->index();

            $table->decimal('delta', 65, 18)->default(0);
            $table->decimal('balance_before', 65, 18)->default(0);
            $table->decimal('balance_after', 65, 18)->default(0);


            $table->string('idempotency_key', 120)->unique();

            // любые детали: курс, комиссия, причина, ip, gateway, operator_id, etc.
            $table->json('meta')->nullable();

            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->timestamps();

            // Индекс для типового запроса "по резерву за период"
            $table->index(['reserve_id', 'occurred_at'], 'idx_rl_reserve_date');

            // Индекс для отчётов по направлению/периоду
            $table->index(['direction_exchange_id', 'occurred_at'], 'idx_rl_direction_date');

            // Индекс для отчётов по действию/периоду
            $table->index(['action', 'occurred_at'], 'idx_rl_action_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserve_ledgers');
    }
};
