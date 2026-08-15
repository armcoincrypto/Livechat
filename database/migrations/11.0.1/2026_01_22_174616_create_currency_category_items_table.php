<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_category_items', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED

            // currency_categories.id = BIGINT UNSIGNED
            $table->unsignedBigInteger('currency_category_id');

            // currencies.id = INT SIGNED (как у тебя: int NOT NULL AUTO_INCREMENT)
            $table->integer('currency_id');

            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->foreign('currency_category_id', 'fk_category_items_category')
                ->references('id')->on('currency_categories')
                ->cascadeOnDelete();

            $table->foreign('currency_id', 'fk_category_items_currency')
                ->references('id')->on('currencies')
                ->cascadeOnDelete();

            $table->unique(['currency_category_id', 'currency_id'], 'uq_currency_category_currency');

            $table->index(['currency_id', 'is_active'], 'idx_currency_active');
            $table->index(['currency_category_id', 'position'], 'idx_category_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_category_items');
    }
};
