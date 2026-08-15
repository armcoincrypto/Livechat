<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserve_links', function (Blueprint $table): void {
            // reserve_id = reserves.id (у тебя int signed) → integer
            $table->integer('reserve_id')->primary();

            $table->integer('parent_reserve_id')->nullable()->index();

            $table->boolean('is_active')->default(true)->index();
            $table->string('note', 191)->nullable();

            $table->timestamps();
            $table->foreign('reserve_id')->references('id')->on('reserves')->cascadeOnDelete();
            $table->foreign('parent_reserve_id')->references('id')->on('reserves')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserve_links');
    }
};
