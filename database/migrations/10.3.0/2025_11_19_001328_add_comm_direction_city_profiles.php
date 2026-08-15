<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direction_city_profiles', function (Blueprint $table) {
            $table->string('add_comm')
                ->nullable()
                ->after('profit_s');
        });
    }

    public function down(): void
    {
        Schema::table('direction_city_profiles', function (Blueprint $table) {
            $table->dropColumn('add_comm');
        });
    }
};
