<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->timestamp('seen_by_visitor_at')->nullable()->after('telegram_reply_to_message_id');
            $table->index(['support_conversation_id', 'seen_by_visitor_at'], 'support_messages_conv_seen_visitor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->dropIndex('support_messages_conv_seen_visitor_idx');
            $table->dropColumn('seen_by_visitor_at');
        });
    }
};
