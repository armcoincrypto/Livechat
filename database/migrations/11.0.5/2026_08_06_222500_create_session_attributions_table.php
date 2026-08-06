<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 11 — privacy-safe anonymous session attribution (additive, nullable).
 *
 * Does not alter financial order columns beyond a nullable FK-like pointer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('session_attributions')) {
            Schema::create('session_attributions', function (Blueprint $table) {
                $table->id();
                $table->string('public_session_id', 64);
                $table->string('first_utm_source', 64)->nullable();
                $table->string('first_utm_medium', 64)->nullable();
                $table->string('first_utm_campaign', 128)->nullable();
                $table->string('first_utm_term', 128)->nullable();
                $table->string('first_utm_content', 128)->nullable();
                $table->string('last_utm_source', 64)->nullable();
                $table->string('last_utm_medium', 64)->nullable();
                $table->string('last_utm_campaign', 128)->nullable();
                $table->string('last_utm_term', 128)->nullable();
                $table->string('last_utm_content', 128)->nullable();
                $table->string('first_referrer', 255)->nullable();
                $table->string('last_referrer', 255)->nullable();
                $table->string('first_landing_path', 255)->nullable();
                $table->string('last_landing_path', 255)->nullable();
                $table->string('locale', 8)->nullable();
                $table->string('device_class', 16)->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique('public_session_id', 'session_attributions_public_session_id_uq');
                $table->index('first_seen_at', 'session_attributions_first_seen_at_idx');
                $table->index('last_seen_at', 'session_attributions_last_seen_at_idx');
            });
        }

        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'session_attribution_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                // Nullable pointer only — no hard FK so missing attribution never blocks orders.
                $table->unsignedBigInteger('session_attribution_id')->nullable()->after('id_referral_link');
                $table->index('session_attribution_id', 'tasks_session_attribution_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'session_attribution_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex('tasks_session_attribution_id_idx');
                $table->dropColumn('session_attribution_id');
            });
        }

        Schema::dropIfExists('session_attributions');
    }
};
