<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_filter', function (Blueprint $table) {
            // ID валюты (signed integer)
            $table->integer('currency_id')->unsigned(false);
            $table->foreign('currency_id', 'currency_filter_currency_id_fk')
                ->references('id')
                ->on('currencies')
                ->cascadeOnDelete();

            // ID фильтра валюты (signed integer)
            $table->unsignedInteger('filter_currency_id');
            $table->foreign('filter_currency_id', 'currency_filter_filter_currency_id_fk')
                ->references('id')->on('filter_currency')
                ->cascadeOnDelete();

            // Когда добавили/изменили связь
            $table->timestamps();

            // Запрещаем дубли связок
            $table->unique(['currency_id', 'filter_currency_id'], 'currency_filter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_filter');
    }
};
