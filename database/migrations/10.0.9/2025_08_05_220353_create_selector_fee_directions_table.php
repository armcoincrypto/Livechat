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
        Schema::create('selector_fee_direction_exchange', function (Blueprint $table) {
            $table->id();

            $table->foreignId('selector_fee_id')
                ->constrained('selector_fees', 'id', 'selector_fee_id_foreign')
                ->cascadeOnDelete();

            $table->integer('direction_exchange_id')->unsigned(false);

            $table->foreign('direction_exchange_id', 'selector_fee_directions_id_foreign')
                ->references('id')->on('direction_exchange')
                ->cascadeOnDelete();

            $table->unique(['selector_fee_id', 'direction_exchange_id'], 'selector_fee_direction_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selector_fee_direction_exchange');
    }
};
