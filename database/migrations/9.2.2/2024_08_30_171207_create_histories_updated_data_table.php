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
        Schema::create('histories_updated_data', function (Blueprint $table) {
            $table->id();
            $table->string('type_update')->nullable();
            $table->integer('count_num')->default(0);
            $table->integer('total_num')->default(0);
            $table->string('time')->nullable();
            $table->string('old_time')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histories_updated_data');
    }
};
