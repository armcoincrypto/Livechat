<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dynamic_config_settings', function (Blueprint $table): void {
            $table->id();

            // Уровень настроек: global, license, exchange, user и т.д.
            $table->string('scope_type', 50);

            // ID сущности, если применимо (null для global)
            $table->unsignedBigInteger('scope_id')->nullable();

            // Ключ настройки (dot-notation допускается)
            $table->string('key', 191);

            // Значение в формате JSON (поддержка дерева + языков)
            $table->json('value');

            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'key'], 'dyn_cfg_scope_key_unique');
            $table->index(['scope_type', 'scope_id'], 'dyn_cfg_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_config_settings');
    }
};
