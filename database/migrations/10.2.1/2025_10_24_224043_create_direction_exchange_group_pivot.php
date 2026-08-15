<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('direction_exchange_group_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id');
            $table->integer('direction_exchange_id'); // signed, как в direction_exchange.id
            $table->timestamps();

            $table->primary(['group_id','direction_exchange_id'], 'deg_map_pk');

            $table->foreign('group_id')
                ->references('id')->on('direction_exchange_groups')
                ->cascadeOnDelete();

            $table->foreign('direction_exchange_id')
                ->references('id')->on('direction_exchange')
                ->cascadeOnDelete();
        });

        // Бэкап со старого поля group_id → в пивот
        DB::statement("
            INSERT IGNORE INTO direction_exchange_group_pivot (group_id, direction_exchange_id, created_at, updated_at)
            SELECT group_id, id, NOW(), NOW()
            FROM direction_exchange
            WHERE group_id IS NOT NULL AND group_id > 0
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('direction_exchange_group_pivot');
    }
};
