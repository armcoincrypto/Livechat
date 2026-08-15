<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies_groups_networks', function (Blueprint $table) {
            if (Schema::hasColumn('currencies_groups_networks', 'is_hidden_list')) {
                $table->dropColumn('is_hidden_list');
            }
        });
    }

    public function down(): void
    {
        Schema::table('currencies_groups_networks', function (Blueprint $table) {
            // Если нужно вернуть колонку обратно
            $table->boolean('is_hidden_list')->default(false);
        });
    }
};
