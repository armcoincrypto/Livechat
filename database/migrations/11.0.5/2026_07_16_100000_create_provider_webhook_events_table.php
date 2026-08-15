<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provider_webhook_events')) {
            return;
        }

        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->index();
            $table->string('event_id', 191);
            $table->string('delivery_id', 191)->nullable()->index();
            $table->string('event_type', 96)->nullable()->index();
            $table->string('provider_payment_id', 191)->nullable()->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->string('payload_hash', 64);
            $table->string('status', 32)->default('received')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'provider_webhook_events_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_events');
    }
};
