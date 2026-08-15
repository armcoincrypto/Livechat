<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dynamic_config_locks', function (Blueprint $table): void {
            $table->id();

            $table->string('scope_type', 50);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('key', 191);

            $table->dateTime('locked_until')->nullable();
            $table->boolean('locked_permanent')->default(false);
            $table->string('reason', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['scope_type', 'scope_id', 'key'], 'dyn_cfg_locks_scope_key_unique');
            $table->index(['scope_type', 'scope_id'], 'dyn_cfg_locks_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_config_locks');
    }
};
