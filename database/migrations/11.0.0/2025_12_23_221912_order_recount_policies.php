<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_recount_policies', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->boolean('is_enabled')->default(true);
            $table->integer('priority')->default(100);

            // global|currency
            $table->string('scope_type', 32)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();

            // ["status-change","cron","manual"]
            $table->json('trigger_types');

            // engine format: [{type, value, ...}]
            $table->json('conditions');

            // engine format: [{type, ...params}]
            $table->json('actions');

            $table->boolean('stop_further')->default(false);
            $table->string('title', 190)->default('');

            $table->timestamps();

            $table->index(['scope_type','scope_id','is_enabled','priority'], 'orp_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_recount_policies');
    }
};
