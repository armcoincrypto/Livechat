<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем уникальные поля под систему идентификации личности.
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {

            // Режим идентификации личности:
            // disabled | always | min_amount | only_new_users | only_unverified
            $table->string('identity_verification_mode', 32)
                ->default('disabled')
                ->comment('Режим идентификации личности');

            // Минимальная сумма для режима min_amount
            $table->string('identity_min_amount')
                ->default(0)
                ->after('identity_verification_mode')
                ->comment('Пороговая сумма для идентификации');

            // Краткий текст (мультиязычный JSON)
            $table->json('identity_text')
                ->nullable()
                ->after('identity_min_amount')
                ->comment('Мультиязычное описание блока идентификации');

            // Подробная инструкция (мультиязычный JSON)
            $table->json('identity_info')
                ->nullable()
                ->after('identity_text')
                ->comment('Мультиязычная инструкция для идентификации личности');
        });
    }

    /**
     * Откат изменений
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn([
                'identity_verification_mode',
                'identity_min_amount',
                'identity_text',
                'identity_info',
            ]);
        });
    }
};
