<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_operator_usage_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('support_conversations')
                ->nullOnDelete();
            $table->unsignedBigInteger('visitor_message_id')->nullable()->index();
            $table->unsignedBigInteger('operator_message_id')->nullable()->index();
            $table->unsignedBigInteger('suggestion_usage_id')->nullable()->index();
            $table->string('intent', 64)->nullable()->index();
            $table->boolean('order_lookup_used')->default(false);
            $table->boolean('direction_lookup_used')->default(false);
            $table->timestamp('draft_generated_at')->nullable()->index();
            $table->timestamp('operator_replied_at')->nullable();
            $table->unsignedInteger('response_time_seconds')->nullable();
            $table->string('operator_decision', 32)->nullable()->index();
            $table->string('suggestion_preview', 160)->nullable();
            $table->string('operator_reply_preview', 160)->nullable();
            $table->string('suggestion_text_hash', 64)->nullable()->index();
            $table->string('operator_text_hash', 64)->nullable();
            $table->decimal('similarity_score', 5, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'visitor_message_id'], 'support_ai_op_usage_conv_visitor_uq');
            $table->index(['draft_generated_at', 'operator_decision'], 'support_ai_op_usage_draft_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_operator_usage_metrics');
    }
};
