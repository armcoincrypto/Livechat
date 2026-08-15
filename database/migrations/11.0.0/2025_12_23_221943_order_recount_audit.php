<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_recount_audit', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('policy_id')->nullable()->index();

            $table->string('decision', 16); // skipped|matched|executed|failed
            $table->string('reason_code', 64)->default('ok');
            $table->json('meta')->nullable();

            $table->string('old_rate', 64)->nullable();
            $table->string('new_rate', 64)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id','created_at'], 'ora_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_recount_audit');
    }
};
