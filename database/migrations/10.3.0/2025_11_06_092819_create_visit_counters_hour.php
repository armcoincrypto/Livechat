<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visit_counters_hour', function (Blueprint $table) {
            // Начало часа в UTC (например: 2025-11-06 14:00:00)
            $table->dateTime('bucket_hour')->primary();

            // Агрегаты за час
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('auth')->default(0);
            $table->unsignedInteger('guest')->default(0);

            $table->timestamps();

            $table->index('bucket_hour', 'vch_bucket_idx');
            $table->index('updated_at',  'vch_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_counters_hour');
    }
};
