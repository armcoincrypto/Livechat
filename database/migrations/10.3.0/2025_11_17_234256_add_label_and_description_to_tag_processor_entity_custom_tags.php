<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_processor_entity_custom_tags', function (Blueprint $table) {
            // Человекочитаемое имя тега
            $table->string('label')
                ->nullable()
                ->after('key');

            // Описание тега
            $table->text('description')
                ->nullable()
                ->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('tag_processor_entity_custom_tags', function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
