<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем JSON-поле freeze_scam в tasks_meta
     */
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->json('freeze_scam')
                ->nullable()
                ->after('user_flag')
                ->comment('Данные блокировки заявки (freeze_scam): тип, причины, дата');
        });
    }

    /**
     * Откат миграции
     */
    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->dropColumn('freeze_scam');
        });
    }
};
