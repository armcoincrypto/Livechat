<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_attachments', function (Blueprint $table): void {
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('caption');
            $table->string('telegram_file_id', 255)->nullable()->after('telegram_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_attachments', function (Blueprint $table): void {
            $table->dropColumn(['telegram_message_id', 'telegram_file_id']);
        });
    }
};
