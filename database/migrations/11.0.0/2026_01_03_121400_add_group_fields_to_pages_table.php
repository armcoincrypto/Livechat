<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {

            // связь с группой (nullable — страница может быть одиночной)
            $table->foreignId('group_id')
                ->nullable()
                ->after('page_id')
                ->constrained('page_groups')
                ->nullOnDelete();

            // сортировка внутри группы
            $table->integer('sort_order')
                ->default(0)
                ->after('group_id');

            // активность страницы (по желанию, но крайне полезно)
            $table->boolean('is_active')
                ->default(true)
                ->after('sort_order');

            // уникальность slug внутри группы
            $table->unique(['group_id', 'page_slug'], 'pages_group_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {

            $table->dropUnique('pages_group_slug_unique');

            $table->dropForeign(['group_id']);
            $table->dropColumn([
                'group_id',
                'sort_order',
                'is_active'
            ]);
        });
    }
};
