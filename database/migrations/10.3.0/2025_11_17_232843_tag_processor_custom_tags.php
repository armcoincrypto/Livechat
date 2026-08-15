<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_processor_custom_tags', function (Blueprint $table) {
            $table->id();

            // Уникальный ключ тега (часть после двоеточия: [custom:key])
            $table->string('key')->unique();

            // Человекочитаемое имя и описание
            $table->string('label')->nullable();
            $table->text('description')->nullable();

            // Логическая группа/категория (marketing, email, footer, system и т.п.)
            $table->string('group')->nullable()->index();

            // Тип значения: string, text, html, markdown, json, number ...
            $table->string('type')->default('string')->index();

            // Scope — где можно использовать тег (email, site, any и т.п.)
            $table->string('scope')->default('any')->index();

            // Основное значение (тело шорткода)
            $table->longText('value')->nullable();

            // Доп. данные в JSON (например, настройки, локаль и т.п.)
            $table->json('meta')->nullable();

            // Статус (включён/выключен)
            $table->boolean('is_active')->default(true)->index();

            // Авторство (если используешь привязку к пользователям)
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_processor_custom_tags');
    }
};
