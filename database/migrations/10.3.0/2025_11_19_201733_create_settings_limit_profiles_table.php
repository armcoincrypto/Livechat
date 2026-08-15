<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица системных профилей лимитов
        Schema::create('settings_limit_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();

            // Лимиты по количеству заявок
            $table->unsignedInteger('max_num_order_user_hour')->default(0);
            $table->unsignedInteger('max_num_order_user_day')->default(0);

            // Гибкий лимит "N заявок за M минут"
            $table->unsignedInteger('order_limit_count')->default(0);
            $table->unsignedInteger('order_limit_minutes')->default(0);

            // Профиль по умолчанию
            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });

        // Привязка профиля к пользователям
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('limit_profile_id')
                ->nullable()
                ->after('id')
                ->constrained('settings_limit_profiles')
                ->nullOnDelete();
        });

        // Привязка профиля к направлениям обмена
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->foreignId('limit_profile_id')
                ->nullable()
                ->after('id')
                ->constrained('settings_limit_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->dropConstrainedForeignId('limit_profile_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('limit_profile_id');
        });

        Schema::dropIfExists('settings_limit_profiles');
    }
};
