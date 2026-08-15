<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies_groups_networks', function (Blueprint $table) {
            $table->tinyInteger('display_type')
                ->default(0)
                ->comment('0 - стандартная группировка, 1 - компактная')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('currencies_groups_networks', function (Blueprint $table) {
            $table->dropColumn('display_type');
        });
    }
};
