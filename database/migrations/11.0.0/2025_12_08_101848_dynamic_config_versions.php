<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dynamic_config_versions', function (Blueprint $table): void {
            $table->id();

            $table->string('scope_type', 50);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('key', 191);

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['scope_type', 'scope_id', 'key'], 'dyn_cfg_versions_scope_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_config_versions');
    }
};
