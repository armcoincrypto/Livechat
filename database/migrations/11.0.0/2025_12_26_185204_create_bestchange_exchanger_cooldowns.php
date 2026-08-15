<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bestchange_exchanger_cooldowns', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('changer_id')->index();
            $table->timestamp('blocked_until')->index();
            $table->string('reason', 191)->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['changer_id'], 'ux_bc_exchanger_cooldown_changer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestchange_exchanger_cooldowns');
    }
};
