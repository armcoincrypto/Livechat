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
        Schema::table('bestchange_directions', function (Blueprint $table) {
            $table->string('exchange_in')->nullable();
            $table->string('exchange_out')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bestchange_directions', function (Blueprint $table) {
            //
        });
    }
};
