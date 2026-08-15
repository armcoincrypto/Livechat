<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extra_out_profile_currencies', function (Blueprint $table) {
            $table->id();

            // Связь с профилем extra_out
            $table->foreignId('profile_id')
                ->constrained('extra_out_profiles')
                ->cascadeOnDelete();


            // Связь с валютой (замени имя таблицы, если нужно)
            $table->integer('currency_id')->unsigned(false);
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

            // Уникальность пары профиль-валюта
            $table->unique(['profile_id', 'currency_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_out_profile_currencies');
    }
};
