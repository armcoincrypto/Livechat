<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем плейсхолдеры для масок реквизитов:
     * - mask_placeholder_char_from — для поля "Со счёта"
     * - mask_placeholder_char_to   — для поля "На счёт"
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            // один символ, но оставим небольшой запас (10), nullable
            $table->string('mask_placeholder_char_from', 10)
                ->nullable()
                ->after('mask_account_from');

            $table->string('mask_placeholder_char_to', 10)
                ->nullable()
                ->after('mask_account_to');
        });
    }

    /**
     * Откат миграции.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn([
                'mask_placeholder_char_from',
                'mask_placeholder_char_to',
            ]);
        });
    }
};
