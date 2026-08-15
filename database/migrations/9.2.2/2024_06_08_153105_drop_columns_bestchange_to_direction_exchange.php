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
            $table->dropColumn([
                'enable_bestchange',
                'id_bestchange_rates',
                'bestchange_position',
                'is_change_bestchange_range',
                'bestchange_range',
                'bestchange_range_from',
                'bestchange_range_to',
                'bestchange_step',
                'bestchange_city',
                'id_bs_alt_parser',
                'bs_alt_parser_course',
                'bestchange_min_reserve',
                'bestchange_max_reserve',
                'bestchange_bl',
                'bestchange_wl',
                'bc_min_sum',
                'bc_max_sum',
                'bc_new_commission',
                'bc_id_new_rate',
                'bc_add_course',
            ]);
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
