<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Удаляем таблицу, если она существует
        Schema::dropIfExists('tasks_user');
    }

    public function down(): void
    {
        // Восстанавливаем таблицу, если нужно откатить миграцию
        Schema::create('tasks_user', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedBigInteger('id_task');
            $table->unsignedBigInteger('id_user');
            $table->decimal('convert_to_usd', 24, 8)->nullable();

            $table->timestamps();

            // Индексы
            $table->index('id_task');
            $table->index('id_user');

            // Внешние ключи (если они были)
            $table->foreign('id_task')
                ->references('id')->on('tasks')
                ->onDelete('cascade');

            $table->foreign('id_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }
};
