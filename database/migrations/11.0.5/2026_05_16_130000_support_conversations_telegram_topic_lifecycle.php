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
            $table->timestamp('telegram_topic_closed_at')->nullable()->after('telegram_forum_topic_created_at');
            $table->timestamp('telegram_topic_reopened_at')->nullable()->after('telegram_topic_closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_topic_closed_at',
                'telegram_topic_reopened_at',
            ]);
        });
    }
};
