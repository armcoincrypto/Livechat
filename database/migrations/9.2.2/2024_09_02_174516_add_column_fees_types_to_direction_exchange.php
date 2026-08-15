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
            $table->string('fix_fee')->default(0);
            $table->string('floating_fee')->default(0);
            $table->integer('fix_fee_time')->default(0);
            $table->integer('floating_fee_time')->default(0);
            $table->string('fix_fee_statuses')->nullable();
            $table->string('floating_fee_statuses')->nullable();
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
