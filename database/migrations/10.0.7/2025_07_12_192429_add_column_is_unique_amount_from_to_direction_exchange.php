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

        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->boolean('is_unique_amount_from')
                ->default(false)
                ->comment('Запрет на создание заявок с одинаковой суммой отдачи');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            //
        });
    }
};
