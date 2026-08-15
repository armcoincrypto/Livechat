<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_payments', function (Blueprint $table) {
            $table->string('network_code', 50)->nullable()->after('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('currency_payments', function (Blueprint $table) {
            $table->dropColumn('network_code');
        });
    }
};
