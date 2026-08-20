<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator claim state for Telegram (and future channels).
 * Does NOT change financial task.status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_operator_assignments')) {
            return;
        }

        Schema::create('order_operator_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->unique();
            $table->unsignedBigInteger('operator_user_id');
            $table->unsignedBigInteger('telegram_user_id')->nullable()->index();
            $table->string('source', 32)->default('telegram');
            $table->timestamp('claimed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['operator_user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_operator_assignments');
    }
};
