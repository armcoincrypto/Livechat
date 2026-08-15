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
        Schema::table('user_wallet_stories', function (Blueprint $table) {
            $table->unsignedInteger('usage_count')->default(1)->after('id_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_wallet_stories', function (Blueprint $table) {
            //
        });
    }
};
