<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 11 / C.3E — privacy-safe attribution lifecycle events (additive).
 *
 * No hard FKs: missing session_attributions or tasks must never block
 * financial status transitions. Unique (task_id, event_type) enforces
 * once-per-order conceptual lifecycle events.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('session_attribution_events')) {
            return;
        }

        Schema::create('session_attribution_events', function (Blueprint $table) {
            $table->id();
            // Nullable pointer — orphan OK after 30-day session prune.
            $table->unsignedBigInteger('session_attribution_id')->nullable();
            $table->unsignedBigInteger('task_id');
            $table->string('event_type', 32);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['task_id', 'event_type'], 'sae_task_event_uq');
            $table->index('session_attribution_id', 'sae_session_attribution_id_idx');
            $table->index('occurred_at', 'sae_occurred_at_idx');
            $table->index(['event_type', 'occurred_at'], 'sae_event_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_attribution_events');
    }
};
