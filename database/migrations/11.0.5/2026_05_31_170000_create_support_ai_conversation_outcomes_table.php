<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_conversation_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('support_conversations')
                ->cascadeOnDelete();
            $table->string('outcome', 32)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('last_operator_message_id')->nullable()->index();
            $table->unsignedBigInteger('last_visitor_message_id')->nullable()->index();
            $table->unsignedInteger('time_to_resolution_seconds')->nullable();
            $table->string('source', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('conversation_id');
            $table->index(['outcome', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_conversation_outcomes');
    }
};
