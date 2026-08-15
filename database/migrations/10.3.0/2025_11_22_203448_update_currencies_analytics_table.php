<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Вносим улучшения в таблицу currencies_analytics:
     * - id делаем PRIMARY KEY AUTO_INCREMENT
     * - in_amount_usd / out_amount_usd переводим в DECIMAL(24,8)
     * - добавляем уникальный индекс по id_currency
     */
    public function up(): void
    {
        // 1. Приводим id к нормальному автоинкрементному PK
        // Если у тебя уже есть PK — этот блок можно адаптировать/упростить
        Schema::table('currencies_analytics', function (Blueprint $table) {
            // В некоторых БД `bigIncrements()->change()` требует doctrine/dbal
            // и наличие PK. Если id уже PK, просто меняем тип.
            $table->bigIncrements('id')->change();
        });

        Schema::table('currencies_analytics', function (Blueprint $table) {
            // 2. Делаем точные DECIMAL для USD-сумм вместо double(8,2)
            $table->decimal('in_amount_usd', 24, 8)
                ->default('0.00000000')
                ->change();

            $table->decimal('out_amount_usd', 24, 8)
                ->default('0.00000000')
                ->change();

            // 3. Гарантируем, что по каждой валюте одна строка
            // Если записи-дубликаты уже есть, перед миграцией их нужно будет разрулить.
            $table->unique('id_currency', 'currencies_analytics_id_currency_unique');
        });
    }

    /**
     * Откат изменений.
     */
    public function down(): void
    {
        Schema::table('currencies_analytics', function (Blueprint $table) {
            // Убираем уникальный индекс с id_currency
            $table->dropUnique('currencies_analytics_id_currency_unique');

            // Возвращаем типы in_amount_usd/out_amount_usd к прежним double(8,2)
            $table->double('in_amount_usd', 8, 2)
                ->default(0.00)
                ->change();

            $table->double('out_amount_usd', 8, 2)
                ->default(0.00)
                ->change();
        });

        // Про откат AUTO_INCREMENT можно либо не заморачиваться,
        // либо вернуть к исходному типу, если он точно известен.
        Schema::table('currencies_analytics', function (Blueprint $table) {
            // Если раньше id был просто BIGINT без AI/PK, можно вернуть так:
            // $table->bigInteger('id')->unsigned()->change();
            // PRIMARY KEY можно вручную дропнуть, если нужен полный откат.
        });
    }
};
