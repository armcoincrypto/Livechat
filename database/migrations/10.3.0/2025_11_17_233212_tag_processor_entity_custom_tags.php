<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_processor_entity_custom_tags', function (Blueprint $table) {
            $table->id();

            // Полиморфная цель: к какой сущности привязан тег
            // Пример: App\Models\Order, App\Models\User и т.п.
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->index(['entity_type', 'entity_id'], 'tp_entity_custom_tags_entity_index');

            // Ключ тега внутри этой сущности (bonus, comment, vip_level)
            $table->string('key');

            // Логическая группа (marketing, internal, finance и т.п.) — для фильтров и удобства
            $table->string('group')->nullable()->index();

            // Тип значения: string, text, html, markdown, json, number ...
            $table->string('type')->default('string')->index();

            // Основное значение
            $table->longText('value')->nullable();

            // Доп. данные в JSON (например, форматирование, локаль и т.д.)
            $table->json('meta')->nullable();

            // Статус
            $table->boolean('is_active')->default(true)->index();

            // Авторство
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // В рамках одной сущности key должен быть уникален
            $table->unique(
                ['entity_type', 'entity_id', 'key'],
                'tp_entity_custom_tags_entity_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_processor_entity_custom_tags');
    }
};
