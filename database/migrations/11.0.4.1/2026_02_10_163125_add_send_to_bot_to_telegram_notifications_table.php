<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем флаг отправки уведомлений ТОЛЬКО в Telegram-бот.
     */
    public function up(): void
    {
        Schema::table('telegram_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('telegram_notifications', 'send_to_bot')) {
                $table->boolean('send_to_bot')
                    ->default(false)
                    ->after('status')
                    ->comment('Если включено — уведомления отправляются только в личный чат бота');
            }
        });
    }

    /**
     * Откат миграции.
     */
    public function down(): void
    {
        Schema::table('telegram_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_notifications', 'send_to_bot')) {
                $table->dropColumn('send_to_bot');
            }
        });
    }
};
