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
        Schema::dropIfExists('rules_pages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('rules_pages', function ($table) {
            $table->id();

            // Если таблица была связующей (pivot) — оставляю базовую структуру
            // При необходимости можешь восстановить точную схему
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();

            $table->timestamps();
        });
    }
};
