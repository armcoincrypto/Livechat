<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_recount_daily_stats', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->date('day');

            // trigger: cron | status-change | manual
            $table->string('trigger', 32);

            // decision: executed | matched | skipped | failed
            $table->string('decision', 16);

            // reason_code: interval_not_reached, locked, ok, etc.
            $table->string('reason_code', 64)->default('ok');

            // scope
            $table->string('scope_type', 16)->default('global'); // global | currency | direction | floating
            $table->unsignedBigInteger('scope_id')->nullable();

            // counters
            $table->unsignedBigInteger('events_count')->default(0);

            // sums for averages
            $table->unsignedBigInteger('perf_ms_sum')->default(0);
            $table->unsignedBigInteger('calc_ms_sum')->default(0);
            $table->unsignedBigInteger('recount_ms_sum')->default(0);

            $table->unsignedBigInteger('perf_ms_count')->default(0);
            $table->unsignedBigInteger('calc_ms_count')->default(0);
            $table->unsignedBigInteger('recount_ms_count')->default(0);

            $table->timestamp('updated_at')->useCurrent();

            $table->unique(
                ['day', 'trigger', 'decision', 'reason_code', 'scope_type', 'scope_id'],
                'ord_daily_unique'
            );

            // индексы для аналитики
            $table->index(['day'], 'ord_daily_day_idx');
            $table->index(['scope_type', 'scope_id'], 'ord_daily_scope_idx');
            $table->index(['trigger'], 'ord_daily_trigger_idx');
            $table->index(['decision'], 'ord_daily_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_recount_daily_stats');
    }
};
