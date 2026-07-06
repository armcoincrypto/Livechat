<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('telegram_forum_topic_id')->nullable()->after('last_operator_display_name');
            $table->timestamp('telegram_forum_topic_created_at')->nullable()->after('telegram_forum_topic_id');

            $table->index('telegram_forum_topic_id', 'support_conversations_telegram_forum_topic_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropIndex('support_conversations_telegram_forum_topic_id_idx');
            $table->dropColumn([
                'telegram_forum_topic_id',
                'telegram_forum_topic_created_at',
            ]);
        });
    }
};
