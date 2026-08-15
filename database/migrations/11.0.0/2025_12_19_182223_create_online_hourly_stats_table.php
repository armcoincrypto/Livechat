<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_hourly_stats', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->dateTime('hour')->unique(); // startOfHour

            $table->unsignedBigInteger('auth_hits')->default(0);
            $table->unsignedBigInteger('guest_hits')->default(0);

            // Снимок конкуррентного онлайна, обновляется командой presence:snapshot
            $table->unsignedInteger('concurrent_last')->default(0);
            $table->unsignedInteger('concurrent_max')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_hourly_stats');
    }
};
