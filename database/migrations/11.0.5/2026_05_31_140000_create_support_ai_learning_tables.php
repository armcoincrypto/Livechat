<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_learning_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('support_conversations')
                ->nullOnDelete();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->string('ai_request_id', 64)->nullable()->index();
            $table->string('intent', 64)->nullable()->index();
            $table->string('conversation_stage', 64)->nullable()->index();
            $table->string('language', 16)->nullable()->index();
            $table->json('suggestions')->nullable();
            $table->json('suggestion_hashes')->nullable();
            $table->unsignedTinyInteger('selected_suggestion_index')->nullable();
            $table->text('operator_reply')->nullable();
            $table->string('operator_reply_hash', 64)->nullable()->index();
            $table->boolean('operator_edited')->default(false);
            $table->decimal('edit_distance_ratio', 5, 4)->nullable();
            $table->string('outcome', 32)->nullable()->index();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->json('safety_flags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'message_id']);
        });

        Schema::create('support_ai_learning_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('candidate_type', 48)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('source', 48)->nullable();
            $table->string('intent', 64)->nullable()->index();
            $table->string('language', 16)->nullable()->index();
            $table->string('title', 191)->nullable();
            $table->text('problem_summary')->nullable();
            $table->text('proposed_rule')->nullable();
            $table->text('proposed_example')->nullable();
            $table->text('before_example')->nullable();
            $table->text('after_example')->nullable();
            $table->json('evidence')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('risk_level', 16)->nullable()->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'candidate_type']);
            $table->index(['intent', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_learning_candidates');
        Schema::dropIfExists('support_ai_learning_events');
    }
};
