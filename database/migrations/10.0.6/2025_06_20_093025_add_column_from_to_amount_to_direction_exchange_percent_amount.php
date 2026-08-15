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
        Schema::table('direction_exchange_percent_amount', function (Blueprint $table) {
            $table->string('from_amount')->default(0);
            $table->string('to_amount')->default(0);
            $table->dropColumn(['amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direction_exchange_percent_amount', function (Blueprint $table) {
            //
        });
    }
};
