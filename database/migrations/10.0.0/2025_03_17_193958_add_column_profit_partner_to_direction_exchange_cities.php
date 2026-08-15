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
        Schema::table('direction_exchange_cities', function (Blueprint $table) {
            $table->string('profit_partner')->default('0');
            $table->string('profit_partner_s')->default('0');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direction_exchange_cities', function (Blueprint $table) {
            //
        });
    }
};
