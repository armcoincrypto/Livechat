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
        Schema::table('wallets_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'id_requisites',
                'account',
                'provider',
                'is_fee_sent'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets_addresses', function (Blueprint $table) {
            //
        });
    }
};
