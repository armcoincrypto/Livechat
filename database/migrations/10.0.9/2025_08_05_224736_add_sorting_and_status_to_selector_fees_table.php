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
        Schema::table('selector_fees', function (Blueprint $table) {
            $table->integer('sorting')->default(0)->after('fee');
            $table->boolean('status')->default(true)->after('sorting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            //
        });
    }
};
