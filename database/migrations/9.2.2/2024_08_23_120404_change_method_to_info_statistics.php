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
        Schema::table('info_statistics', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('value')->change();
            $table->dropColumn(['account_number', 'type', 'is_automatic']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('info_statistics', function (Blueprint $table) {
            //
        });
    }
};
