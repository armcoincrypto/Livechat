<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profit_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // Название профиля: "Стандарт 3%", "High-risk 5%" и т.д.
            $table->decimal('profit', 10, 4)->default(0);   // Процент прибыли, %
            $table->decimal('profit_s', 10, 4)->default(0); // Фикс/доп. параметр S
            $table->boolean('is_active')->default(true);    // Можно выключать профиль
            $table->string('scope')->default('direction');  // 'direction', 'city', 'global' — если хочешь общий реестр
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
