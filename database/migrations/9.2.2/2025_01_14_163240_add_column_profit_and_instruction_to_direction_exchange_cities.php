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
            $table->string('profit')->default(0);
            $table->longText('instruction')->nullable();
            $table->longText('information')->nullable();
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
