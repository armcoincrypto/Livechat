<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_categories', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED

            $table->string('code', 64)->unique();
            $table->json('title')->nullable();

            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_categories');
    }
};
