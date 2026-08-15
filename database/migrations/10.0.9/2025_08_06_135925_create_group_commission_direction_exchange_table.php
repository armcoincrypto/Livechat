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
        Schema::table('group_commission', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true)->change(); // ← это важно!
        });


        Schema::create('group_commission_direction_exchange', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_commission_id')
                ->constrained('group_commission')
                ->cascadeOnDelete();

            $table->integer('direction_exchange_id')->unsigned(false);

            $table->foreign('direction_exchange_id', 'group_commission_directions_id_foreign')
                ->references('id')->on('direction_exchange')
                ->cascadeOnDelete();

            $table->unique(['group_commission_id', 'direction_exchange_id'], 'group_direction_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_commission_direction_exchange');
    }
};
