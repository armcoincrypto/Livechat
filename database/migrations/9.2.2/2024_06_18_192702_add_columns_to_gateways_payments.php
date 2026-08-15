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
        Schema::table('gateways_payments', function (Blueprint $table) {
            $table->string('day_limit_amount_pay')->default(0);
            $table->string('month_limit_amount_pay')->default(0);
            $table->string('min_amount_for_per_order')->default(0);
            $table->string('max_amount_for_per_order')->default(0);
            $table->integer('day_limit_pay')->default(0);
            $table->integer('month_limit_pay')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateways_payments', function (Blueprint $table) {
            //
        });
    }
};
