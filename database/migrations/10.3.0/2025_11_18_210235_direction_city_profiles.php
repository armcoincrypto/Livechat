<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direction_city_profiles', function (Blueprint $table) {
            $table->id();

            // Человекочитаемое название профиля (для админки)
            $table->string('name', 191);

            // Необязательный технический код (для поиска / логики)
            $table->string('code', 191)->nullable()->unique();

            // Основные параметры прибыли (строковые значения)
            $table->string('profit', 191)->nullable();      // %
            $table->string('profit_s', 191)->nullable();    // фикс. сумма

            // Статус профиля: включён / выключен
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direction_city_profiles');
    }
};
