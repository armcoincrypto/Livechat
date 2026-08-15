<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Удаляем устаревшие таблицы авторизации.
     *
     * Таблицы больше не используются:
     * - user_auth → заменена auth_audit_events
     * - admin_auth_log → заменена auth_audit_events
     */
    public function up(): void
    {
        if (Schema::hasTable('user_auth')) {
            Schema::drop('user_auth');
        }

        if (Schema::hasTable('admin_auth_log')) {
            Schema::drop('admin_auth_log');
        }
    }

    /**
     * Восстановление таблиц (rollback).
     *
     * ⚠️ ВАЖНО:
     * Структура восстановлена минимально, только чтобы rollback не падал.
     * Исторические данные при этом, разумеется, не вернутся.
     */
    public function down(): void
    {
        if (! Schema::hasTable('user_auth')) {
            Schema::create('user_auth', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_user')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('old_ip_address', 64)->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('iso_code', 8)->nullable();
                $table->timestamps();

                $table->index('id_user');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('admin_auth_log')) {
            Schema::create('admin_auth_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_user')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('prev_ip_address', 64)->nullable();
                $table->unsignedTinyInteger('is_successful')->default(0);
                $table->timestamps();

                $table->index('id_user');
                $table->index('created_at');
            });
        }
    }
};
