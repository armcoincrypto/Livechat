<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_category_items', function (Blueprint $table) {
            // Одна валюта может быть только в одной категории
            $table->unique('currency_id', 'uq_currency_category_items_currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('currency_category_items', function (Blueprint $table) {
            $table->dropUnique('uq_currency_category_items_currency_id');
        });
    }
};
