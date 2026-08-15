<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_provider_sessions')) {
            return;
        }

        Schema::create('kyc_provider_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider', 32);
            $table->string('provider_session_id', 128);
            $table->string('provider_workflow_id', 128)->nullable();
            $table->uuid('provider_reference')->index();
            $table->string('normalized_status', 64)->default('not_started');
            $table->string('provider_status', 64)->nullable();
            $table->text('verification_url')->nullable();
            $table->string('last_event_id', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_session_id'], 'kyc_provider_sessions_provider_session_unique');
            $table->index(['user_id', 'provider', 'normalized_status'], 'kyc_provider_sessions_user_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_provider_sessions');
    }
};
