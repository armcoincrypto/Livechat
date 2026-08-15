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
            $table->unique('telegram_inbound_message_id', 'support_messages_telegram_inbound_uid');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->dropUnique('support_messages_telegram_inbound_uid');
        });
    }
};
