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
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('pay_day_limit_amount')->default(0);
            $table->string('pay_month_limit_amount')->default(0);
            $table->string('pay_min_amount_for_order')->default(0);
            $table->string('pay_max_amount_for_order')->default(0);
            $table->integer('pay_day_limit')->default(0);
            $table->integer('pay_month_limit')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            //
        });
    }
};
