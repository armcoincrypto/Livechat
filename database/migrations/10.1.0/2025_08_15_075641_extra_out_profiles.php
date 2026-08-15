<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extra_out_profiles', function (Blueprint $table) {
            $table->id();

            // Название/лейбл для полей реквизитов (то, что показывается пользователю)
            $table->longText('field_label')->nullable();

            // Включено/выключено использование доп. реквизитов (фича-флаг)
            $table->boolean('is_enabled')->default(true);

            // Минимальная сумма одной выплаты (на один реквизит)
            $table->string('min_payout_amount')->default(0);

            // Минимальная сумма OUT, начиная с которой активируется механизм доп. реквизитов
            $table->string('min_trigger_amount')->default(0);

            // Лимит количества реквизитов, которые можно добавить пользователю
            $table->unsignedSmallInteger('max_fields')->default(20);

            // Описание / подсказка для UI
            $table->longText('description')->nullable();

            $table->longText('button_name')->nullable();

            $table->timestamps();

            // Индексы для частых фильтров
            $table->index(['is_enabled']);
            $table->index(['min_trigger_amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_out_profiles');
    }
};
