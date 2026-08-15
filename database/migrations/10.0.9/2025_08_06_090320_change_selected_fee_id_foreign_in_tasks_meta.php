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
            // Сначала удаляем старый внешний ключ и колонку
            $table->dropForeign(['selected_fee_id']);
            $table->dropColumn('selected_fee_id');
        });

        Schema::table('tasks_meta', function (Blueprint $table) {
            // Теперь создаём заново с корректной таблицей selector_fees
            $table->unsignedBigInteger('selected_fee_id')->nullable()->after('telegram_data');

            $table->foreign('selected_fee_id')
                ->references('id')
                ->on('selector_fees')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->dropForeign(['selected_fee_id']);
            $table->dropColumn('selected_fee_id');
        });

        Schema::table('tasks_meta', function (Blueprint $table) {
            // Возвращаем старую колонку и ключ, если понадобится rollback
            $table->unsignedBigInteger('selected_fee_id')->nullable()->after('telegram_data');

            $table->foreign('selected_fee_id')
                ->references('id')
                ->on('direction_exchange_selector_fee')
                ->nullOnDelete();
        });
    }
};
