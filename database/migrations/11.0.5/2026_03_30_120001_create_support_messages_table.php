<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_conversation_id')
                ->constrained('support_conversations')
                ->cascadeOnDelete();
            $table->string('sender_type', 32)->index();
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('telegram_outbound_message_id')->nullable();
            $table->unsignedBigInteger('telegram_inbound_message_id')->nullable();
            $table->unsignedBigInteger('telegram_reply_to_message_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['support_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
