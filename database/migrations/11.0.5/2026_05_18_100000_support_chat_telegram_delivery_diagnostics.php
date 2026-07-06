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
            $table->timestamp('telegram_delivery_failed_at')->nullable()->after('telegram_outbound_message_id');
            $table->string('telegram_delivery_error', 255)->nullable()->after('telegram_delivery_failed_at');
            $table->index('telegram_delivery_failed_at', 'support_messages_tg_delivery_failed_idx');
        });

        Schema::table('support_attachments', function (Blueprint $table): void {
            $table->timestamp('telegram_delivery_failed_at')->nullable()->after('telegram_file_id');
            $table->string('telegram_delivery_error', 255)->nullable()->after('telegram_delivery_failed_at');
            $table->index('telegram_delivery_failed_at', 'support_attachments_tg_delivery_failed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_attachments', function (Blueprint $table): void {
            $table->dropIndex('support_attachments_tg_delivery_failed_idx');
            $table->dropColumn(['telegram_delivery_failed_at', 'telegram_delivery_error']);
        });

        Schema::table('support_messages', function (Blueprint $table): void {
            $table->dropIndex('support_messages_tg_delivery_failed_idx');
            $table->dropColumn(['telegram_delivery_failed_at', 'telegram_delivery_error']);
        });
    }
};
