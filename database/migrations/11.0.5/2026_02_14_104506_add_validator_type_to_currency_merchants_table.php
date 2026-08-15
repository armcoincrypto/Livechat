<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет поле validator_type в pivot-таблицу currency_merchants.
 *
 * Назначение:
 * - Хранение типа валидатора для связки "Валюта ↔ Мерчант".
 * - Используется для дополнительной проверки адреса при генерации реквизитов.
 *
 * Особенности:
 * - Поле nullable (валидатор может быть не выбран).
 * - Длина 64 символа достаточно для технического идентификатора валидатора.
 */
return new class extends Migration
{
    /**
     * Выполнение миграции.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('currency_merchants', function (Blueprint $table): void {
            if (!Schema::hasColumn('currency_merchants', 'validator_type')) {
                $table->string('validator_type', 64)
                    ->nullable()
                    ->after('network_code')
                    ->comment('Тип валидатора для проверки адреса');
            }
        });
    }

    /**
     * Откат миграции.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('currency_merchants', function (Blueprint $table): void {
            if (Schema::hasColumn('currency_merchants', 'validator_type')) {
                $table->dropColumn('validator_type');
            }
        });
    }
};
