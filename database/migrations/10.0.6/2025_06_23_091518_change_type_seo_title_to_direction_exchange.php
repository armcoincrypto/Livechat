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
            $table->text('seo_title')->change();
            $table->longText('seo_description')->change();
            $table->longText('seo_keywords')->change();
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
