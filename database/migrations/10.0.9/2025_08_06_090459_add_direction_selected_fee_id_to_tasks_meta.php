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
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->unsignedBigInteger('direction_selected_fee_id')->nullable()->after('selected_fee_id');

            $table->foreign('direction_selected_fee_id')
                ->references('id')
                ->on('direction_exchange_selector_fee')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->dropForeign(['direction_selected_fee_id']);
            $table->dropColumn('direction_selected_fee_id');
        });
    }
};
