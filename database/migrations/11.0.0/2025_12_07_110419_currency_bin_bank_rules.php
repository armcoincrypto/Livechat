<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица правил по банкам для конкретных валют,
 * используемых модулем проверки BIN (BinInspector).
 */
return new class extends Migration {
    /**
     * Создание таблицы.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('currency_bin_bank_rules', function (Blueprint $table) {
            $table->id();

            // Валюта, к которой относится правило
            // ВАЖНО: тип должен совпадать с currencies.id (int, без unsigned)
            $table->integer('currency_id');

            // Направление:
            // in  — входящие переводы (клиент отдаёт)
            // out — исходящие переводы (клиент получает)
            $table->string('direction', 3); // 'in' | 'out'

            // Тип правила:
            // allow — разрешённый банк
            // block — запрещённый банк
            $table->string('mode', 6); // 'allow' | 'block'

            // Название банка или маска поиска по названию
            $table->string('bank_name', 191);

            $table->timestamps();

            $table->foreign('currency_id')
                ->references('id')
                ->on('currencies')
                ->onDelete('cascade');

            $table->index(['currency_id', 'direction', 'mode'], 'currency_bin_bank_rules_idx');

            // Чтобы не было дубликатов правил по одной валюте/направлению/режиму/банку
            $table->unique(
                ['currency_id', 'direction', 'mode', 'bank_name'],
                'currency_bin_bank_rules_unique'
            );
        });
    }

    /**
     * Откат миграции.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_bin_bank_rules');
    }
};
