<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_suggestion_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('support_conversations')
                ->nullOnDelete();
            $table->unsignedBigInteger('visitor_message_id')->nullable()->index();
            $table->unsignedTinyInteger('suggestion_id')->nullable()->index();
            $table->unsignedBigInteger('operator_message_id')->nullable()->index();
            $table->unsignedBigInteger('learning_event_id')->nullable()->index();
            $table->string('decision', 32)->index();
            $table->unsignedInteger('edit_distance')->nullable();
            $table->decimal('similarity_score', 5, 4)->nullable();
            $table->string('matched_by', 48)->nullable()->index();
            $table->string('suggestion_text_hash', 64)->nullable()->index();
            $table->string('operator_text_hash', 64)->nullable()->index();
            $table->string('suggestion_preview', 320)->nullable();
            $table->string('operator_reply_preview', 320)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('operator_message_id');
            $table->index(['conversation_id', 'created_at']);
            $table->index(['decision', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_suggestion_usages');
    }
};
