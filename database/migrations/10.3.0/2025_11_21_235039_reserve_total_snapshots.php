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
        Schema::create('reserve_total_snapshots', function (Blueprint $table) {
            $table->id();

            // Время, к которому относится снапшот (по сути "срез" на момент)
            $table->timestamp('snapshot_at')->index();

            // Общий резерв в USD
            $table->decimal('total_usd', 36, 18);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
