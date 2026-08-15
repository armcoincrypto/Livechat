<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            $table->unsignedBigInteger('city_id')->nullable()->after('city_name');
        });
    }

    public function down()
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            $table->dropColumn('city_id');
        });
    }
};
