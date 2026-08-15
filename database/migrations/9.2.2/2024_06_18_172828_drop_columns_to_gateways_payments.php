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
        Schema::table('gateways_payments', function (Blueprint $table) {
            $table->dropColumn([
                'min_confirm',
                'max_register_blockchain',
                'max_first_confirm_blockchain',
                'is_mass_payouts',
                'hide_check_balance',
                'currency_code',
                'site_account',
                'method_pay',
                'country_code',
                'num_request',
                'priority_fee',
                'code_currency',
                'exchange_buy',
                'exchange_fee',
                'exchange_buy_type',
                'time_in_force',
                'exchange_buy_curr',
                'is_auto_take_fee',
                'bank_name',
                'id_gateways',
                'is_subtract'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateways_payments', function (Blueprint $table) {
            //
        });
    }
};
