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
        Schema::create('currencies_analytics_daily', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->date('date')->index();
            $table->unsignedBigInteger('id_currency')->index();

            // суммы в номинале валюты
            $table->string('in_amount', 191)->nullable();
            $table->string('out_amount', 191)->nullable();

            // счётчики
            $table->unsignedInteger('in_count')->default(0);
            $table->unsignedInteger('out_count')->default(0);

            // суммы в USD (фиксированные на момент расчёта дня)
            $table->decimal('in_amount_usd', 24, 8)->default('0.00000000');
            $table->decimal('out_amount_usd', 24, 8)->default('0.00000000');

            $table->timestamps();

            $table->unique(['date', 'id_currency'], 'currencies_analytics_daily_date_currency_unique');
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
