<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Удаляет устаревшие таблицы iex_settings и settings.
 *
 * Эти таблицы больше не используются системой, перенесён весь функционал
 * в новые конфигурационные модули. Удаление безопасно.
 */
return new class extends Migration
{
    /**
     * Выполняет удаление таблиц.
     */
    public function up(): void
    {
        // Удаляем таблицу iex_settings, если существует
        if (Schema::hasTable('iex_settings')) {
            Schema::dropIfExists('iex_settings');
        }

        // Удаляем таблицу settings, если существует
        if (Schema::hasTable('settings')) {
            Schema::dropIfExists('settings');
        }
    }

    /**
     * Откат — создаёт пустые таблицы исключительно для совместимости,
     * если потребуется откатить миграцию.
     */
    public function down(): void
    {
        if (!Schema::hasTable('iex_settings')) {
            Schema::create('iex_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }
};
