<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_groups', function (Blueprint $table) {
            $table->id();

            // slug группы (/pages/{groupSlug})
            $table->string('slug')->unique();

            // мультиязычное название и описание
            $table->json('title');
            $table->json('description')->nullable();

            // сортировка групп
            $table->integer('sort_order')->default(0);

            // активность группы
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_groups');
    }
};
