<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_conversation_id')
                ->constrained('support_conversations')
                ->cascadeOnDelete();
            $table->foreignId('support_message_id')
                ->nullable()
                ->constrained('support_messages')
                ->nullOnDelete();
            $table->string('sender_type', 32)->index();
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->nullable()->index();
            $table->string('caption', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['support_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
    }
};
