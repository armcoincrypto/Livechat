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
        Schema::table('gateways_merchants', function (Blueprint $table) {
            $table->dropColumn([
                'is_disable_code_currency',
                'id_gateways',
                'is_check_from_shot',
                'is_merchant_log',
                'is_check_api',
                'is_pay_commission',
                'method_pay',
                'fixed_fee',
                'is_card_found',
                'bank_name',
                'site_account',
                'code_currency',
                'min_confirm',
                'max_register_blockchain',
                'max_first_confirm_blockchain',
                'exchange_fee',
                'type_pay',
                'process_method'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateways_merchants', function (Blueprint $table) {
            //
        });
    }
};
