<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkbox_agreements', function (Blueprint $table) {
            $table->id();
            $table->text('label');
            $table->longText('description')->nullable();
            $table->string('link')->nullable();
            $table->string('page_type')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->boolean('checked')->default(false);
            $table->boolean('required')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sorting')->default(0);
            $table->timestamps();

            // Индексация для удобства поиска и выборки
            $table->index(['page_type', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkbox_agreements');
    }
};
