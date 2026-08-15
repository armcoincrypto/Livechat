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
        Schema::create('direction_exchange_selector_fee', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_direction_exchange');
            $table->string('fee')->default(0);
            $table->text('name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direction_exchange_selector_fee');
    }
};
