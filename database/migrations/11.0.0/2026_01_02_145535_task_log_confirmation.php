<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('task_log_confirmation');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('task_log_confirmation', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }
};
