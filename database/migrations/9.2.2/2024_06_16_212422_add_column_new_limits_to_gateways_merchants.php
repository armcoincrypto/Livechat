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
        Schema::table('gateways_merchants', function (Blueprint $table) {
            $table->string('month_limit_amount_merchant')->default(0);
            $table->string('min_amount_for_per_order')->default(0);
            $table->string('max_amount_for_per_order')->default(0);
            $table->string('month_limit_merchant')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateways_merchants', function (Blueprint $table) {
            //
        });
    }
};
