<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Добавляем поля для верификации личности на уровне направления обмена.
     */
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            // Тип верификации личности:
            // 0 — использовать настройки валюты
            // 1 — отдельные правила для направления
            $table->unsignedTinyInteger('identity_verification_type')
                ->default(0)
                ->comment('Тип верификации личности: 0 — по умолчанию (из валюты), 1 — от направления')
                ->after('card_verification_rules'); // подкорректируй, если нужно другое место

            // JSON-настройки верификации личности:
            // {
            //   "mode": "disabled|always|min_amount|only_new_users|only_unverified",
            //   "min_amount": 0,
            //   ... любые будущие флаги ...
            // }
            $table->json('identity_verification_rules')
                ->nullable()
                ->comment('JSON-настройки верификации личности (режим, пороги и др.)')
                ->after('identity_verification_type');

            // Краткий текст для формы (по локалям)
            $table->json('identity_text')
                ->nullable()
                ->comment('Краткое описание проверки личности (мультиязычно)')
                ->after('identity_verification_rules');

            // Подробная инструкция (по локалям)
            $table->json('identity_info')
                ->nullable()
                ->comment('Подробная инструкция по верификации личности (мультиязычно)')
                ->after('identity_text');
        });
    }

    /**
     * Откат изменений.
     */
    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->dropColumn([
                'identity_verification_type',
                'identity_verification_rules',
                'identity_text',
                'identity_info',
            ]);
        });
    }
};
