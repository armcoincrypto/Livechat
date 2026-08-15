<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->index();
            $table->string('visitor_name');
            $table->string('visitor_email');
            $table->string('visitor_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('access_token_hash', 64);
            $table->unsignedSmallInteger('access_token_version')->default(1);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->string('last_message_sender_type', 32)->nullable();
            $table->timestamp('last_visitor_message_at')->nullable();
            $table->timestamp('last_operator_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversations');
    }
};
