<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_exchange_totals', function (Blueprint $table) {
            // Меняем float -> decimal
            $table->decimal('exchange_usd', 24, 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_exchange_totals', function (Blueprint $table) {
            // Возвращаем обратно float, если нужно
            $table->float('exchange_usd')->nullable()->change();
        });
    }
};
