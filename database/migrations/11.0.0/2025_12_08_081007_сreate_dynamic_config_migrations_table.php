<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица для учёта применённых миграций настроек DynamicConfig.
 *
 * Аналогична стандартной таблице migrations, но:
 * - может учитывать scope (global, license, exchange, user);
 * - позволяет повторно применять миграции для разных scope.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('dynamic_config_migrations', function (Blueprint $table): void {
            $table->id();

            // Имя миграции, например: 2025_12_10_000001_add_security_flags
            $table->string('migration', 191);

            // Scope настроек: global, license, exchange, user
            $table->string('scope_type', 50)->default('global');

            // ID записи scope, если применимо (для global = null)
            $table->unsignedBigInteger('scope_id')->nullable();

            // Batch, как в стандартных миграциях Laravel
            $table->unsignedInteger('batch');

            $table->timestamps();

            $table->index(['scope_type', 'scope_id'], 'dyn_cfg_migrations_scope_index');
            $table->unique(['migration', 'scope_type', 'scope_id'], 'dyn_cfg_migrations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_config_migrations');
    }
};
