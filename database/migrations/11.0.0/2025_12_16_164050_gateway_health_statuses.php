<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gateway_health_statuses', function (Blueprint $table) {
            $table->id();

            // merchant | payment
            $table->string('type', 16)->index();

            // id мерчанта или выплаты
            $table->unsignedBigInteger('entity_id')->index();

            // alias шлюза
            $table->string('gateway_alias', 64)->index();

            // ok | degraded | fail
            $table->string('status', 16)->index();

            $table->smallInteger('http_status')->nullable();
            $table->integer('latency_ms')->nullable();

            $table->string('message')->nullable();

            // сколько раз подряд fail
            $table->unsignedInteger('fail_streak')->default(0);

            $table->timestamp('last_ok_at')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->unique(['type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_health_statuses');
    }
};
